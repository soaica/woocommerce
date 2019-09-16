<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'webdemo' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', '123456789' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'm|E?}U^EBK-#|34;O;eQCp ;E}6S!aarSk$KtVe+X8&-zkn03$NH9t1VpYu%k=bf' );
define( 'SECURE_AUTH_KEY',  'HVW7I![@$`Zq3aK4`?Y5rnyVR Gw,+,L^I4Ruu+dgn`bYW)n^,Y!cd CEa~s/w^V' );
define( 'LOGGED_IN_KEY',    's6QmI)t@n(YYa@b,r1p4E{en~PeD`=,J(:/Kq[eA%@Jmc9r^!W a,B.$I[M|*42B' );
define( 'NONCE_KEY',        ';]h`UM&Du  }[<Psw6Py^,uL`S=4wXr9wyIwxA%^TVj^4UsuEZ9D=VJ>X,#|<}Ml' );
define( 'AUTH_SALT',        'm/V3wwn/vBy8I3QsN8?w>{uRhRG*j&bsOp]tC@2<O;TPD9gc9h[Albcwj~T!WHlS' );
define( 'SECURE_AUTH_SALT', 'qVlKqm*1ZX6Ja<CY])}=-yc7suTbcr1~WOz0 Ac_ukmABRE,9L%a~uoQCzGKKC3w' );
define( 'LOGGED_IN_SALT',   ')t#,hz>O&*T{HH1BYr_/wcY,i:,D$(d([v& (J+uspIMqA[{-3etlw+j5Ze,_1vj' );
define( 'NONCE_SALT',       '6P9Us:Z+r/2I9bhHQmz[Z@&)-GSd!KLAqfGgQza]`94{W%q2zCTR1C0f=$+G.C-~' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
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
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

/** Sets up WordPress vars and included files. */
require_once( ABSPATH . 'wp-settings.php' );
