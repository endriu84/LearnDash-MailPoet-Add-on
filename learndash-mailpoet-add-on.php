<?php
/**
Plugin Name:       LearnDash MailPoet Add on
Description:       This is a simple LearnDash and MailPoet base plugin for auto subscribing learndash group users to appropriate  mailpoet mailing list
Version:           1.0
Author:            Andrzej Misiewicz
Author URI:        http://misiewicz.it/
License:            MIT License
License URI:        http://opensource.org/licenses/MIT
*/


// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

/**
 * required plugins: LearnDash, MailPoet
 */
if (!class_exists('LearnDash_MailPoet_Add_on') && is_plugin_active('sfwd-lms/sfwd_lms.php') && is_plugin_active('mailpoet/mailpoet.php')) {

    /**
     * Main LearnDash MailPoet Add on class
     */
    final class LearnDash_MailPoet_Add_on
    {

        /**
         * Constructor
         * Hooks into LearnDash plugin
         */
        public function __construct()
        {
            add_action('ld_group_postdata_updated', array( $this, 'subscribe_users'), 10, 3);
        }

        /**
         * Subscribe users from group to mailing list
         * If user was deleted from group, it also delete him from mailing list
         * 
         * @param  int $post_id       learndash group id
         * @param  array $group_leaders  group leaders wp user ids
         * @param  array $group_users   group wp users ids
         * @return bool                true or false
         */
        public function subscribe_users($post_id, $group_leaders, $group_users)
        {
            $post = get_post($post_id);

            $segment = $this->create_or_update_segment_by_name($post->post_title);

            $subscribers = array();
            foreach ($segment->subscribers()->findResultSet() as $s) {
                $subscribers[ $s->wp_user_id ] = $s;
            }

            foreach ($group_users as $wp_user_id) {
                if (!isset($subscribers[ $wp_user_id ])) {
                    $wp_user = get_userdata($wp_user_id);

                    $subscriber = \MailPoet\Models\Subscriber::findOne($wp_user->user_email);

                    // all wp user should also be mailpoet subscriber (wordpress users list), but in case it's not
                    if ($subscriber) {
                        $segment->addSubscriber($subscriber->id);
                    } else {
                        return false;
                    }
                } else {
                    unset($subscribers[ $wp_user_id ]);
                }
            }

            foreach ($subscribers as $wp_id => $s) {
                \MailPoet\Models\SubscriberSegment::deleteSubscriptions($s, array( $segment->id));
            }

            return true;
        }

        /**
         * Finds segment (mailing list) by name
         * If segment already exists, it return its class, otherwise it create new segment class based on name parameter
         * 
         * @param  string $name learndash group name
         * @return object       \MailPoet\Model\Segment object or false
         */
        public function create_or_update_segment_by_name($name)
        {
            $segment = false;
            $all_segments = \MailPoet\Models\Segment::getSegmentsWithSubscriberCount();

            foreach ($all_segments as $seg) {
                if ($seg['name'] == $name) {
                    unset($seg['subscribers']);
                    $segment = \MailPoet\Models\Segment::createOrUpdate($seg);
                    break;
                }
            }

            if (!$segment) {
                $segment = \MailPoet\Models\Segment::createOrUpdate(array( 'name' => $name,
                                                                           'description' => 'Lista utworzona automatycznie dla: '.$name ));
            }

            return $segment;
        }
    }

    new LearnDash_MailPoet_Add_on();
}
