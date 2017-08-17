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
define('DB_NAME', 'karus');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'DiEArb;nDH92g`2JfcQY/GFJbH2mQQgiS?1,@:{>V%LNP`8+AMy2#e+2Ze43qraw');
define('SECURE_AUTH_KEY',  ',ICF;%xaq<HT6cLarr7>lvpeUzaK=[W~>B7pX~@iy8MxcQO8:wEw;. 9pI#hDD#P');
define('LOGGED_IN_KEY',    'A-OuXwn%ce:7er77-G?S#%wjEfFLmzePL|GH0wPs;OEI.]8@t=W:Je[P<,k^r.||');
define('NONCE_KEY',        'hS)4OTzzY(=c-nxPE&2s/)2f|nlWR?VWvLl}qg]3ld>wUD}iusslN2<>)wcTp,TB');
define('AUTH_SALT',        ':,,#PzO*,;9#HO;@6/`:uK%r#%J8AM0asX`8x.pRo|cI=4R35fhy.^~{#<H7:/`[');
define('SECURE_AUTH_SALT', '-5CCpF9m j?6[4*SWJCs#S{QW+WdKv1od3TQyo),W0lHanz[-O{eg(rv~~8wuA=1');
define('LOGGED_IN_SALT',   'ETP~~a@5wVOO,PnwO*z-^oDu/YfjIxYa1*W0aFA6K|e^ES%[rR|JSU&eXC?SKT^[');
define('NONCE_SALT',       'og6(|1V/w6=m5]$<2qj@%j]7$83MFudy%{:RlT>_Q_W3f,AhYI>Lxr6@#E [rGR8');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

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
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
