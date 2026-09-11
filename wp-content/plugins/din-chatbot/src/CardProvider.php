<?php

namespace DinStudio\DinChatbot;

interface CardProvider {
    /**
     * @param array<mixed> $variation_ids
     * @return array<int,array<string,mixed>>
     */
    public function cards( array $variation_ids, int $limit = 3 ): array;
}
