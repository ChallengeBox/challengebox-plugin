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
define('DB_NAME', 'dev');
/** MySQL database username */
define('DB_USER', 'dev');
/** MySQL database password */
define('DB_PASSWORD', '63YUevJAnNjuAKAPBTd');
/** MySQL hostname */
define('DB_HOST', 'localhost');
/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');
/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

// Call this from crontab instead.
define('DISABLE_WP_CRON', true);
define('FORCE_SSL_ADMIN', true);
/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '+dm[WzHVOZGVa4<ZKWDjWXU`}|*C-!>|(p%!FxfDzH{)1W3A&`#!<_!cE6JCNWHO');
define('SECURE_AUTH_KEY',  '&?Ya[QE3=}Hne8I`<2eQ_Wq_S1SkyIB`{Swh?a44j>4Vi>I{S6t9bRLg4/]XZeIH');
define('LOGGED_IN_KEY',    '$lI;L@8adw[IOyG@]4p+bY>%0I6D`w9fXop2{ch0Ay^j[mR{I{{w|!G#r-JL!E*x');
define('NONCE_KEY',        ';`J1Fj+cm-zNh[@Y:=|%#5Dz@@UyH-B2oFA&vV[%$|^;,GR0$$eYUt[%FJ9D_U `');
define('AUTH_SALT',        '. 3>KfiXpuN<0,`r9L-b#P?n&T0@x_;,tDw^I=F3tN!@8qv]mQ^Ye*=).?|@w*1q');
define('SECURE_AUTH_SALT', 'P$E2FaHT;J2R*nIl/wnlBdW#HoN|5=FLcBWq-Nr:b%veou/`2(trT0NZ.!#5gCc8');
define('LOGGED_IN_SALT',   '^5&}$Fl%l&lF~-t|f?.(k5[[SNC]E15<%?7exFMs;(,+rh9L{P_+W1X8jR>J<)L5');
define('NONCE_SALT',       '*5+~oC!!hP~~_, Z8_[GXB^ 9}$]z21?r3z,Q0*K92PVJ0c{*2x;CKkatZv_UizJ');
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
define('WP_DEBUG', true);
/* That's all, stop editing! Happy blogging. */
/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');
/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
