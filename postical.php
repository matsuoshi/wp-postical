<?php

/**
 * Plugin Name: postical (iCal exporter)
 * Version: 1.0.1
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
		add_action('admin_init', array($this, 'admin_init'));
		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('admin_notices', array($this, 'admin_notices'));
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
		/** @global WP_Query $wp_query */
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
		if (! empty($_GET['future'])) {
			if ($_GET['future'] == get_option('postical-future')) {
				$args['post_status'][] = 'future';
			}
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
		/** @global WP_Post $post */
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
	 * add admin options page
	 */
	public function admin_menu()
	{
		add_options_page(
			'postical',
			'postical',
			'manage_options',
			'postical_setting',
			array($this, 'setting_page')
		);
	}


	/**
	 * admin form validation
	 */
	public function admin_init()
	{
		if (! empty($_POST['postical-setting'])) {
			if (check_admin_referer('my-nonce-key', 'postical-setting')) {
				if (! empty($_POST['postical-future'])) {
					update_option('postical-future', $_POST['postical-future']);
				}
				else {
					update_option('postical-future', '');
				}

				set_transient('postical-setting-message', 'updated.', 10);
				wp_safe_redirect(menu_page_url('postical_setting', false));
			}
		}
	}


	/**
	 * setting page
	 */
	public function setting_page()
	{
		$future_key = get_option('postical-future');
		$ical_url = get_home_url() . '/' . $this->_ical_name;
		$ical_future_url = '';
		$checked = '';

		if (! empty($future_key)) {
			// enable future posts
			$checked = 'checked';
			$ical_future_url = "{$ical_url}?future={$future_key}";
		}
		else {
			$future_key = bin2hex(openssl_random_pseudo_bytes(20));
		}

		?>
		<div class="wrap">
			<h2>postical</h2>

			<form id="postical-setting" method="post" action="">
				<?php wp_nonce_field('my-nonce-key', 'postical-setting'); ?>

				<p>
					<label>
						<input type="checkbox" name="postical-future" value="<?php echo esc_attr($future_key) ?>" <?php echo $checked ?>>
						enable future posts
					</label>
				</p>

				<dl style="margin: 2em 0;">
					<dt>iCal URL</dt>
					<dd>
						<a href="<?php echo esc_attr($ical_url)?>"><?php echo esc_html($ical_url)?></a>
					</dd>
					<?php if ($checked) : ?>
						<dt>iCal URL include future posts</dt>
						<dd>
							<a href="<?php echo esc_attr($ical_future_url)?>"><?php echo esc_html($ical_future_url)?></a>
						</dd>
					<?php endif; ?>
				</dl>

				<p>
					<input type="submit" value="Save" class="button button-primary button-large">
				</p>
			</form>
		</div>
	<?php
	}


	/**
	 * output message
	 */
	public function admin_notices()
	{
		$message = get_transient('postical-setting-message');
		if ($message) :
			?>
			<div class="updated">
				<ul>
					<li><?php echo esc_html($message) ?></li>
				</ul>
			</div>
			<?php
		endif;
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
