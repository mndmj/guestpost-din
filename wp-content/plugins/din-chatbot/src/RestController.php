<?php

namespace DinStudio\DinChatbot;

final class RestController {
    private const SESSION_LIMIT = 10;
    private const AUTH_LIMIT = 30;
    private const WINDOW = MINUTE_IN_SECONDS;

    private $clock;
    private $transient_writer;
    private $rate_lock_acquirer;
    private \SplObjectStorage $preauthorized_requests;

    public function __construct( private SessionRepository $sessions, private AutomatedChatService $chat, ?callable $clock = null, ?callable $transient_writer = null, ?callable $rate_lock_acquirer = null ) {
        $this->clock = $clock;
        $this->transient_writer = $transient_writer;
        $this->rate_lock_acquirer = $rate_lock_acquirer;
        $this->preauthorized_requests = new \SplObjectStorage();
    }

    public function register(): void {
        $this->register_pre_dispatch_filter();
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        $this->register_pre_dispatch_filter();
        $routes = [
            '/session' => [ 'method' => 'create_session', 'args' => [
                'client_id' => [ 'required' => true, 'type' => 'string', 'validate_callback' => [ $this, 'validate_client_id' ] ],
            ] ],
            '/message' => [ 'method' => 'message', 'args' => [
                'session_id' => [ 'required' => false, 'type' => 'integer', 'minimum' => 1 ],
                'text' => [ 'required' => true, 'type' => 'string', 'validate_callback' => [ $this, 'validate_text' ] ],
            ] ],
            '/action' => [ 'method' => 'action', 'args' => [
                'session_id' => [ 'required' => false, 'type' => 'integer', 'minimum' => 1 ],
                'type' => [ 'required' => true, 'type' => 'string', 'enum' => [ 'topic', 'rule', 'restart', 'contact' ] ],
                'target_id' => [ 'required' => false, 'type' => 'integer', 'minimum' => 1 ],
            ] ],
            '/restart' => [ 'method' => 'restart', 'args' => [
                'session_id' => [ 'required' => false, 'type' => 'integer', 'minimum' => 1 ],
            ] ],
        ];
        foreach ( $routes as $route => $definition ) {
            register_rest_route( 'din-chatbot/v1', $route, [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [ $this, $definition['method'] ],
                'permission_callback' => static fn (): bool => true,
                'args' => $definition['args'],
            ] );
        }
    }

    /** Runs before WordPress validates route body arguments, preserving 401 precedence for invalid buyer tokens. */
    public function authorize_before_validation( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ): mixed {
        $route = strtolower( (string) $request->get_route() );
        if ( null !== $result || 'POST' !== strtoupper( $request->get_method() ) || ! in_array( $route, [ '/din-chatbot/v1/message', '/din-chatbot/v1/action', '/din-chatbot/v1/restart' ], true ) ) {
            return $result;
        }
        $session = $this->authenticate_header( $request );
        if ( $session instanceof \WP_Error ) {
            return $session;
        }
        $submitted_id = $this->positive_integer( $this->value( $request, 'session_id' ) );
        if ( $submitted_id > 0 && $submitted_id !== $session['id'] ) {
            return $this->error( 'din_chatbot_unauthorized', 'Unauthorized.', 401 );
        }
        $token = $request->get_header( 'X-Din-Chatbot-Token' );
        if ( ! $this->allow( 'auth', hash( 'sha256', $token ), self::AUTH_LIMIT ) ) {
            return $this->error( 'din_chatbot_rate_limited', 'Too many requests.', 429 );
        }
        $this->preauthorized_requests->attach( $request, $session );
        return null;
    }

    public function create_session( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $client_id = $this->value( $request, 'client_id' );
        if ( ! is_string( $client_id ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{8,64}$/D', $client_id ) ) {
            return $this->error( 'din_chatbot_invalid_client', 'Invalid client ID.', 400 );
        }
        if ( ! $this->allow( 'session', hash( 'sha256', $client_id ), self::SESSION_LIMIT ) ) {
            return $this->error( 'din_chatbot_rate_limited', 'Too many requests.', 429 );
        }
        try {
            $session = $this->sessions->create();
            return new \WP_REST_Response( [ 'session_id' => $session['id'], 'token' => $session['token'], 'status' => 'automated' ], 201 );
        } catch ( \Throwable ) {
            return $this->error( 'din_chatbot_unavailable', 'Chatbot is temporarily unavailable.', 503 );
        }
    }

    public function message( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $session = $this->authorized_session( $request );
        if ( $session instanceof \WP_Error ) {
            return $session;
        }
        $text = $this->valid_text( $this->value( $request, 'text' ) );
        if ( null === $text ) {
            return $this->error( 'din_chatbot_invalid_text', 'Invalid message text.', 400 );
        }
        try {
            return rest_ensure_response( $this->chat->message( $session['id'], $text ) );
        } catch ( \InvalidArgumentException ) {
            return $this->error( 'din_chatbot_invalid_request', 'Invalid request.', 400 );
        } catch ( \Throwable ) {
            return $this->error( 'din_chatbot_unavailable', 'Chatbot is temporarily unavailable.', 503 );
        }
    }

    public function action( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $session = $this->authorized_session( $request );
        if ( $session instanceof \WP_Error ) {
            return $session;
        }
        $type = $this->value( $request, 'type' );
        if ( ! is_string( $type ) || ! in_array( $type, [ 'topic', 'rule', 'restart', 'contact' ], true ) ) {
            return $this->error( 'din_chatbot_invalid_action', 'Invalid action.', 400 );
        }
        $target_id = 0;
        if ( in_array( $type, [ 'topic', 'rule' ], true ) ) {
            $target_id = $this->positive_integer( $this->value( $request, 'target_id' ) );
            if ( $target_id < 1 ) {
                return $this->error( 'din_chatbot_invalid_action', 'Invalid action.', 400 );
            }
        }
        try {
            return rest_ensure_response( $this->chat->action( $session['id'], $type, $target_id ) );
        } catch ( \InvalidArgumentException ) {
            return $this->error( 'din_chatbot_invalid_action', 'Invalid action.', 400 );
        } catch ( \Throwable ) {
            return $this->error( 'din_chatbot_unavailable', 'Chatbot is temporarily unavailable.', 503 );
        }
    }

    public function restart( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $session = $this->authorized_session( $request );
        if ( $session instanceof \WP_Error ) {
            return $session;
        }
        try {
            return rest_ensure_response( $this->chat->restart( $session['id'] ) );
        } catch ( \InvalidArgumentException ) {
            return $this->error( 'din_chatbot_invalid_request', 'Invalid request.', 400 );
        } catch ( \Throwable ) {
            return $this->error( 'din_chatbot_unavailable', 'Chatbot is temporarily unavailable.', 503 );
        }
    }

    /** @return array{id:int,state:array{status:string,topic_id:int,last_rule_id:int,repeat_count:int,no_match_count:int}}|\WP_Error */
    private function authorized_session( \WP_REST_Request $request ): array|\WP_Error {
        if ( $this->preauthorized_requests->contains( $request ) ) {
            $session = $this->preauthorized_requests[ $request ];
            $this->preauthorized_requests->detach( $request );
            return $session;
        }
        $session = $this->authenticate_header( $request );
        if ( $session instanceof \WP_Error ) {
            return $session;
        }
        $submitted_id = $this->value( $request, 'session_id' );
        if ( null !== $submitted_id && $this->positive_integer( $submitted_id ) !== $session['id'] ) {
            return $this->error( 'din_chatbot_unauthorized', 'Unauthorized.', 401 );
        }
        $token = $request->get_header( 'X-Din-Chatbot-Token' );
        if ( ! $this->allow( 'auth', hash( 'sha256', $token ), self::AUTH_LIMIT ) ) {
            return $this->error( 'din_chatbot_rate_limited', 'Too many requests.', 429 );
        }
        return $session;
    }

    /** @return array{id:int,state:array{status:string,topic_id:int,last_rule_id:int,repeat_count:int,no_match_count:int}}|\WP_Error */
    private function authenticate_header( \WP_REST_Request $request ): array|\WP_Error {
        $token = $request->get_header( 'X-Din-Chatbot-Token' );
        if ( ! is_string( $token ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $token ) ) {
            return $this->error( 'din_chatbot_unauthorized', 'Unauthorized.', 401 );
        }
        $session = $this->sessions->find_by_token( $token );
        if ( null === $session ) {
            return $this->error( 'din_chatbot_unauthorized', 'Unauthorized.', 401 );
        }
        return $session;
    }

    /** Serializes one hashed fixed-window counter with a MySQL named lock. */
    private function allow( string $scope, string $identifier_hash, int $limit ): bool {
        $key = 'din_chatbot_rate_' . $scope . '_' . $identifier_hash;
        $release = $this->acquire_rate_lock( self::rate_lock_name( $scope, $identifier_hash ) );
        if ( null === $release ) {
            return false;
        }

        $allowed = false;
        $released = false;
        try {
            $value = get_transient( $key );
            $now = $this->now();
            $started_at = is_array( $value ) && isset( $value['window_started_at'] ) ? (int) $value['window_started_at'] : 0;
            $count = is_array( $value ) && isset( $value['count'] ) ? (int) $value['count'] : 0;
            if ( $started_at < 1 || $started_at > $now || $now >= $started_at + self::WINDOW ) {
                $started_at = $now;
                $count = 0;
            }
            if ( $count < $limit ) {
                $ttl = max( 1, ( $started_at + self::WINDOW ) - $now );
                $next = [ 'window_started_at' => $started_at, 'count' => $count + 1 ];
                $allowed = $this->write_transient( $key, $next, $ttl );
            }
        } catch ( \Throwable ) {
            $allowed = false;
        } finally {
            try {
                $released = $release();
            } catch ( \Throwable ) {
                $released = false;
            }
        }
        return $allowed && $released;
    }

    public static function rate_lock_name( string $scope, string $identifier_hash ): string {
        return 'din_cb_' . substr( hash( 'sha256', $scope . "\n" . $identifier_hash ), 0, 48 );
    }

    /** @return callable():bool|null */
    private function acquire_rate_lock( string $lock_name ): ?callable {
        if ( null !== $this->rate_lock_acquirer ) {
            $release = call_user_func( $this->rate_lock_acquirer, $lock_name );
            return is_callable( $release ) ? $release : null;
        }
        global $wpdb;
        $acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, 2 ) );
        if ( '1' !== (string) $acquired ) {
            return null;
        }
        return static function () use ( $wpdb, $lock_name ): bool {
            return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
        };
    }

    /** @param array{window_started_at:int,count:int} $value */
    private function write_transient( string $key, array $value, int $ttl ): bool {
        return null !== $this->transient_writer ? (bool) call_user_func( $this->transient_writer, $key, $value, $ttl ) : set_transient( $key, $value, $ttl );
    }

    private function now(): int {
        return null !== $this->clock ? (int) call_user_func( $this->clock ) : time();
    }

    private function register_pre_dispatch_filter(): void {
        static $registered = [];
        $key = spl_object_id( $this );
        if ( isset( $registered[ $key ] ) ) {
            return;
        }
        add_filter( 'rest_pre_dispatch', [ $this, 'authorize_before_validation' ], 10, 3 );
        $registered[ $key ] = true;
    }

    private function valid_text( mixed $value ): ?string {
        if ( ! is_string( $value ) || 1 !== preg_match( '//u', $value ) ) {
            return null;
        }
        $text = preg_replace( '/^\s+|\s+$/u', '', $value );
        if ( ! is_string( $text ) || '' === $text ) {
            return null;
        }
        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : preg_match_all( '/./us', $text );
        return $length >= 1 && $length <= 1000 ? $text : null;
    }

    public function validate_client_id( mixed $value ): bool {
        return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_-]{8,64}$/D', $value );
    }

    public function validate_text( mixed $value ): bool {
        return null !== $this->valid_text( $value );
    }

    private function positive_integer( mixed $value ): int {
        if ( is_int( $value ) ) {
            return $value > 0 ? $value : 0;
        }
        if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
            return 0;
        }
        $integer = (int) $value;
        return (string) $integer === $value ? $integer : 0;
    }

    private function value( \WP_REST_Request $request, string $key ): mixed {
        $json = $request->get_json_params();
        if ( is_array( $json ) && array_key_exists( $key, $json ) ) {
            return $json[ $key ];
        }
        $body = $request->get_body_params();
        return array_key_exists( $key, $body ) ? $body[ $key ] : null;
    }

    private function error( string $code, string $message, int $status ): \WP_Error {
        return new \WP_Error( $code, $message, [ 'status' => $status ] );
    }
}
