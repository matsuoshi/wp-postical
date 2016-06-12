<?php

/**
 * Plugin Name: postical (iCal exporter)
 * Version: 0.5
 * Description: export the posts schedules as iCal format. access to <strong>http://YOUR-WP-ADDRESS/postical</strong>
 * Author: h.matsuo
 * Author URI: http://github.com/matsuoshi
 * Plugin URI: http://github.com/matsuoshi/postical
 * Text Domain: postical
 * @package Postical
 */

class Postical
{
	private $_ical_name = 'postical';


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
	 * init plugin
	 */
	public function init()
	{
		add_rewrite_endpoint($this->_ical_name, EP_ROOT);
		add_action('template_redirect', array($this, 'index'));
	}


	/**
	 * postical main
	 */
	public function index()
	{
		global $wp_query;

		if (! isset($wp_query->query[$this->_ical_name])) {
			return;
		}

		if (! empty($wp_query->query[$this->_ical_name])) {
			$wp_query->set_404();
			status_header(404);
			return;
		}

		$args = array(
			'post_status' => array('publish'),
			'numberposts' => 50,
		);
		if ($_GET['future'] === 'true') {
			// todo: setting page
			$args['post_status'][] = 'future';
		}

		$posts = $this->get_posts($args);

		$output = $this->create_ical($posts);

		$this->output_ical($output);

		exit;
	}


	/**
	 * get posts
	 * @param $args
	 * @return mixed
	 */
	private function get_posts($args)
	{
		return get_posts($args + array(
			'orderby' => 'post_date',
			'order' => 'DESC',
		));
	}


	/**
	 * create iCal format data
	 * @param $data
	 * @return string
	 */
	private function create_ical($data)
	{
		// get base url
		$parsed_url = parse_url(get_home_url());
		$base_url = $parsed_url['host'];
		if (!empty($parsed_url['path'])) {
			$base_url .= $parsed_url['path'];
		}

		// header
		$blog_name = get_bloginfo('name');
		$output = <<< _HEREDOC_
BEGIN:VCALENDAR
PRODID:{$blog_name}
VERSION:2.0

_HEREDOC_;

		// posts
		global $post;

		foreach ($data as $post) {
			setup_postdata($post);

			$start_time = date_i18n('Ymd\THis\Z', strtotime($post->post_date_gmt));
			$title = get_the_title();
			$description = get_the_permalink() . '\nby ' . get_the_author() . '\n' . get_the_excerpt();
			$uid = esc_html("{$post->ID}@{$base_url}");

			$output .= <<< _HEREDOC_
BEGIN:VEVENT
DTSTART:{$start_time}
DURATION:PT1H
SUMMARY:{$title}
DESCRIPTION:{$description}
UID:{$uid}
END:VEVENT

_HEREDOC_;
		}

		// footer
		$output .= <<< _HEREDOC_
END:VCALENDAR

_HEREDOC_;

		return $output;
	}


	/**
	 * output iCal
	 * @param $output
	 */
	private function output_ical($output)
	{
		header('Content-type: text/calendar; charset=utf-8');
		header('Content-Disposition: attachment; filename=postical.ics');
		echo $output;
	}


	/**
	 * activate plugin
	 */
	public function activate()
	{
		update_option('postical_activated', true);
		add_rewrite_endpoint($this->_ical_name, EP_ROOT);
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
	public function delete_option($option)
	{
		if ($option === 'rewrite_rules' && get_option('postical_activated')) {
			add_rewrite_endpoint($this->_ical_name, EP_ROOT);
		}
	}
}

new Postical();
