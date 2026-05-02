<?php
/**
 * Kiosk check-in template.
 *
 * Standalone full-screen page loaded by KioskEndpoint::render_template().
 * No theme header/footer — outputs a complete HTML document.
 *
 * Variables provided by the calling scope:
 *   $kiosk_data (array)  — serialized as window.gymKiosk
 *
 * @package Gym_Core
 * @since   3.3.0
 */

declare( strict_types=1 );

/** @var array $kiosk_data */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: brand name */
			__( 'Check in — %s', 'gym-core' ),
			\Gym_Core\Utilities\Brand::name()
		)
	);
	?>
</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Inter+Tight:wght@500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;1,9..144,500;1,9..144,600&family=JetBrains+Mono:wght@400;500&display=swap">
<?php wp_head(); ?>
</head>
<body>
<div id="root"></div>

<script>
/* Kiosk bootstrap data — injected by KioskEndpoint::build_kiosk_data() */
window.gymKiosk = <?php echo wp_json_encode( $kiosk_data ); ?>;
</script>

<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>

<script type="text/babel" src="<?php echo esc_url( GYM_CORE_URL . 'assets/js/hp-shared.jsx' ); ?>"></script>
<script type="text/babel" src="<?php echo esc_url( GYM_CORE_URL . 'assets/js/kiosk.jsx' ); ?>"></script>
<script type="text/babel">
  ReactDOM.createRoot(document.getElementById('root')).render(React.createElement(KioskApp));
</script>

<?php wp_footer(); ?>
</body>
</html>
