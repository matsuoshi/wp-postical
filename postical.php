<?php

/**
 * Plugin Name: postical
 * Version: 0.2
 * Description: export the posts schedules as iCal format. access to <strong>http://YOUR-WP-ADDRESS/postical/</strong>
 * Author: h.matsuo
 * Author URI: http://github.com/matsuoshi
 * Plugin URI: http://github.com/matsuoshi/postical
 * Text Domain: postical
 * Domain Path: /languages
 * @package Postical
 */

class Postical
{
	/**
	 * Postical constructor.
	 */
	public function __construct()
	{
		register_activation_hook(__FILE__, array($this, 'activate'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));
		add_action('init', array($this, 'init'));
		add_action('delete_option', array($this, 'delete_option'), 10, 1);
	}

	/**
	 * activate plugin
	 */
	public function activate()
	{
		update_option('postical_activated', true);
		add_rewrite_endpoint('postical', EP_ROOT);
		flush_rewrite_rules();
	}

	/**
	 * deactivate plugin
	 */
	public function deactivate()
	{
		delete_option('postical_activated');
		flush_rewrite_rules();
	}

	/**
	 * call flush_rewrite_rules() by other module
	 * @param $option
	 */
	function delete_option($option)
	{
		if ($option === 'rewrite_rules' && get_option('postical_activated')) {
			add_rewrite_endpoint('postical', EP_ROOT);
		}
	}

	/**
	 * init plugin
	 */
	public function init()
	{
		add_rewrite_endpoint('postical', EP_ROOT);
		add_action('template_redirect', array($this, 'index'));
	}

	/**
	 * postical main
	 */
	public function index()
	{
		global $wp_query;

		if (! isset($wp_query->query['postical'])) {
			$wp_query->set_404();
			status_header(404);
			return;
		}

		$post_status = array('publish');
		if ($wp_query->query['postical'] === 'future') {
			$post_status[] = 'future';
		}

		$posts = get_posts(array(
			'post_status' => $post_status,
			'orderby' => 'post_date',
			'order' => 'DESC',
			'numberposts' => 50,
		));

		$this->output($posts);

		exit;
	}

	/**
	 * output iCal data
	 * @param $posts
	 */
	private function output($posts)
	{
		$parsed_url = parse_url(get_home_url());
		$base_url = $parsed_url['host'];
		if (! empty($parsed_url['path'])) {
			$base_url .= $parsed_url['path'];
		}

		?>
BEGIN:VCALENDAR
PRODID:<?php echo get_bloginfo('name') . "\n"; ?>
VERSION:2.0
<?php foreach ($posts as $post) : setup_postdata($post); ?>
BEGIN:VEVENT
DTSTART:<?php echo date_i18n('Ymd\THi00\Z', strtotime($post->post_date_gmt)) . "\n" ?>
DURATION:PT1H
SUMMARY:<?php echo '[' . get_the_author() . '] ' . get_the_title() . "\n" ?>
DESCRIPTION:<?php echo get_the_permalink() . ' \n' . get_the_excerpt() . "\n" ?>
UID:<?php echo esc_html("{$post->ID}@{$base_url}") . "\n"; ?>
END:VEVENT
<?php endforeach; ?>
END:VCALENDAR
		<?php
	}
}

new Postical();
