<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'qwgrhxkr_wp331' );

/** Database password */
define( 'DB_PASSWORD', 'U@hop4S1(7' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY', '-*[9RXT)g=6GSUh|9E^WL}[+t+V343+TnWnx?6NNR_E-o8^tE8HKL=SCoW$;z*Lp' );
define( 'SECURE_AUTH_KEY', '0bl30G-L2s<AfNWnl&7aQN 4#~~,t>5e`~,Nh-R{.!AqVe#zk-@8<z{/GL}J%j-t' );
define( 'LOGGED_IN_KEY', '9U*g`E[$gRNay{dS`+(h4!l]iMFF)pT.-{,t-:rYj+}VP*++.I.g?_DkYDZq  Y+' );
define( 'NONCE_KEY', 'H,MIC*wRi=pW/ZJ;LVtq:!}mz~mDa%0-$YBG4&fRUZ_[1quqzN!*W~Z74mVk+:Rf' );
define( 'AUTH_SALT', ',1}IJ~$j9=[jt/LGvGT=z&S$08r0{|=w|sy#-.E 8oNIh,B4!ZO5;}]Z, %liW9j' );
define( 'SECURE_AUTH_SALT', '_smrNh~FOehy0]L&+54il+c]xZQWkzZaVhO|H+ex wv|lt+y=sfpG|_f&AQG!(&3' );
define( 'LOGGED_IN_SALT', 'c;FX;K&kI0[tH:$l+83sj86:~(jLj8_Ak-euguN;-B[2}kqZ+Kqmmrr+btDJJ61&' );
define( 'NONCE_SALT', '+ydo-WAP^@,/4vEJ7lXF9lwD 3.wcp;L `QdG{ePE1Q,7S/y@V+_-11Bkn<|?pp2' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );

// define( 'WP_HOME', 'https://guestpost.nextdin.com' );
// define( 'WP_SITEURL', 'https://guestpost.nextdin.com' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
