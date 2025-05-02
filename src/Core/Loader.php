<?php
/**
 * Register all actions and filters for the plugin
 *
 * @package    GiVendor\GiPlugin\Core
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Core;

/**
 * Loader - Maintains and registers all hooks for the plugin
 *
 * This class maintains a list of all hooks that are registered throughout
 * the plugin, and registers them with the WordPress API as needed.
 *
 * @package    GiVendor\GiPlugin\Core
 * @author     Gisela <email@example.com>
 * @since      0.1.0
 */
class Loader {

    /**
     * The array of actions registered with WordPress.
     *
     * @since    0.1.0
     * @access   protected
     * @var      array    $actions    The actions registered with WordPress to fire when the plugin loads.
     */
    protected $actions;

    /**
     * The array of filters registered with WordPress.
     *
     * @since    0.1.0
     * @access   protected
     * @var      array    $filters    The filters registered with WordPress to fire when the plugin loads.
     */
    protected $filters;

    /**
     * The array of shortcodes registered with WordPress.
     *
     * @since    0.1.0
     * @access   protected
     * @var      array    $shortcodes    The shortcodes registered with WordPress.
     */
    protected $shortcodes;

    /**
     * Initialize the collections used to maintain the actions and filters.
     *
     * @since    0.1.0
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
        $this->shortcodes = array();
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @since    0.1.0
     * @param    string               $hook             The name of the WordPress action that is being registered.
     * @param    object               $component        A reference to the instance of the object on which the action is defined.
     * @param    string               $callback         The name of the function definition on the $component.
     * @param    int                  $priority         Optional. The priority at which the function should be fired. Default is 10.
     * @param    int                  $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function addAction($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @since    0.1.0
     * @param    string               $hook             The name of the WordPress filter that is being registered.
     * @param    object               $component        A reference to the instance of the object on which the filter is defined.
     * @param    string               $callback         The name of the function definition on the $component.
     * @param    int                  $priority         Optional. The priority at which the function should be fired. Default is 10.
     * @param    int                  $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function addFilter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new shortcode to the collection to be registered with WordPress.
     *
     * @since    0.1.0
     * @param    string               $tag              The name of the shortcode.
     * @param    object               $component        A reference to the instance of the object on which the shortcode is defined.
     * @param    string               $callback         The name of the function definition on the $component.
     */
    public function addShortcode($tag, $component, $callback) {
        $this->shortcodes = $this->addShortcodeInternal($this->shortcodes, $tag, $component, $callback);
    }

    /**
     * A utility function that is used to register the actions and hooks into a single
     * collection.
     *
     * @since    0.1.0
     * @access   private
     * @param    array                $hooks            The collection of hooks that is being registered (that is, actions or filters).
     * @param    string               $hook             The name of the WordPress filter that is being registered.
     * @param    object               $component        A reference to the instance of the object on which the filter is defined.
     * @param    string               $callback         The name of the function definition on the $component.
     * @param    int                  $priority         The priority at which the function should be fired.
     * @param    int                  $accepted_args    The number of arguments that should be passed to the $callback.
     * @return   array                                  The collection of actions and filters registered with WordPress.
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * A utility function that is used to register the shortcodes into a single
     * collection.
     *
     * @since    0.1.0
     * @access   private
     * @param    array                $shortcodes       The collection of shortcodes that is being registered.
     * @param    string               $tag              The name of the shortcode.
     * @param    object               $component        A reference to the instance of the object on which the shortcode is defined.
     * @param    string               $callback         The name of the function definition on the $component.
     * @return   array                                  The collection of shortcodes registered with WordPress.
     */
    private function addShortcodeInternal($shortcodes, $tag, $component, $callback) {
        $shortcodes[] = array(
            'tag'           => $tag,
            'component'     => $component,
            'callback'      => $callback
        );

        return $shortcodes;
    }

    /**
     * Register the filters and actions with WordPress.
     *
     * @since    0.1.0
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->shortcodes as $shortcode) {
            add_shortcode($shortcode['tag'], array($shortcode['component'], $shortcode['callback']));
        }
    }
}