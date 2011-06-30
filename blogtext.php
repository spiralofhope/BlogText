<?php
/*
Plugin Name: BlogText for WordPress
Plugin URI: http://wordpress.org/extend/plugins/blogtext/
Description: Allows you to write your posts and pages with an alternative, easy-to-learn, and fast-to-type syntax
Version: 0.9.1.2
Author: Sebastian Krysmanski
Author URI: http://mayastudios.com
*/

#########################################################################################
#
# Copyright 2010-2011  Maya Studios (http://www.mayastudios.com)
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#########################################################################################


require_once(dirname(__FILE__).'/api/commons.php');

require_once(dirname(__FILE__).'/util.php');
require_once(dirname(__FILE__).'/settings.php');
require_once(dirname(__FILE__).'/error-checking.php');
require_once(dirname(__FILE__).'/adminbar.php');

require_once(dirname(__FILE__).'/markup/blogtext_markup.php');
require_once(dirname(__FILE__).'/settings-page.php');
require_once(dirname(__FILE__).'/editor/editor.php');


class BlogTextPlugin extends MSCL_AbstractPlugin {
  private $main_options_page;

  protected function __construct() {
    parent::__construct();

    // Create error notifier
    new BlogTextErrorNotifier();

    // Create adminbar menu
    // NOTE: Must run after the "init" action
    new BlogTextAdminBarMenu();

    // add convert filter
    // NOTE: We can't use the "the_content_feed" filter here as its being applied after running the
    //   "the_content" filter. The same is true for "the_excerpt".
    add_filter('the_content', array($this, 'convert_content'), 5);
    
    //add_filter('get_the_excerpt', array($this, 'convert_excerpt'), 5);
    //add_filter('the_excerpt_rss', array($this, 'convert_excerpt_rss'), 5);
    //add_filter('comment_text', array($this, 'convert_content'), 5);

    $this->add_stylesheets();
    $this->add_javascripts();

    add_action('wp_head', array($this, 'insert_custom_css'));
  }

  public function wordpress_initialize() {
    // NOTE: Create option page here (after "init") so that the theme has already loaded and "content_width"
    //   is available.
    if (is_admin()) {
      // We're in the backend. Create option page. It registers itself.
      $this->main_options_page = new BlogTextSettingsPage();
    }

    // Set content width
    $width = BlogTextSettings::get_content_width();
    if ($width > 0) {
      global $content_width;
      $content_width = $width;
    }
  }

  public static function get_instance() {
    static $instance = null;
    if ($instance === null) {
      $instance = new BlogTextPlugin();
    }
    return $instance;
  }

  public function convert_content($content) {
    return $this->convert_blogtext($content, false);
  }

  /*public function convert_excerpt($content) {
    return $this->convert_blogtext($content, true);
  }*/

  public function convert_blogtext($content, $is_excerpt) {
    global $post;

    if (!BlogTextPostSettings::get_use_blogtext($post)) {
      // Don't use BlogText for this post.
      return $content;
    }

    try {
      $markup = new BlogTextMarkup();
      if (is_preview()) {
        $is_excerpt = false;
        $render_type = AbstractTextMarkup::RENDER_KIND_PREVIEW;
      } else if (is_feed()) {
        $render_type = AbstractTextMarkup::RENDER_KIND_RSS;
      } else {
        $render_type = AbstractTextMarkup::RENDER_KIND_REGULAR;
      }
      return $markup->convert_post_to_html($post, $content, $render_type, $is_excerpt);
    } catch (Exception $e) {
      print MSCL_ErrorHandling::format_exception($e);
      // exit here as the exception may come from some static constructor that is only executed once
      exit;
    }
  }

  protected function get_main_options_page() {
    return $this->main_options_page;
  }
  
  private function add_stylesheets() {
    if (BlogTextSettings::use_default_css()) {
      $this->add_frontend_stylesheet('style/default.css');
    }

    if (BlogTextSettings::use_default_filetype_icons()) {
      $this->add_frontend_stylesheet('style/fileicons.css');
    }

    $geshi_style = BlogTextSettings::get_geshi_theme();
    if ($geshi_style != BlogTextSettings::OWN_GESHI_STYLE) {
      $this->add_frontend_stylesheet("style/geshi-css/$geshi_style.css", 'geshi');
    }

    BlogTextEditor::insert_css_files($this);
  }

  private function add_javascripts() {
    $this->add_frontend_script('js/blogtext.js');
  }

  public function insert_custom_css() {
    if (is_admin()) {
      return;
    }

    $custom_css = trim(BlogTextSettings::get_custom_css());
    if (empty($custom_css)) {
      return;
    }

    echo '<style type="text/css">'."\n$custom_css\n</style>\n";
  }
}

// create plugin
BlogTextPlugin::get_instance();
?>
