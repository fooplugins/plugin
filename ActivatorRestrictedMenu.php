<?php

namespace FooPlugins\Shared\Admin;

class ActivatorRestrictedMenu {

    private string   $pluginFile;
    private string   $optionKey;
    private $onAllowedCallback;

    /**
     * @param string   $pluginFile         Full path to main plugin file (__FILE__).
     * @param string   $optionKey          Single option key that stores activator + allowed users.
     * @param callable $onAllowedCallback  Callback executed when current user is allowed.
     */
    public function __construct( string $pluginFile, string $optionKey, callable $onAllowedCallback ) {
        $this->pluginFile        = $pluginFile;
        $this->optionKey         = $optionKey;
        $this->onAllowedCallback = $onAllowedCallback;

        // Wire hooks automatically.
        register_activation_hook( $this->pluginFile, [ $this, 'on_activation' ] );
        add_action( 'admin_menu', [ $this, 'maybe_execute_callback' ] );
    }

    /**
     * Called on plugin activation.
     *
     * @param bool $network_wide
     */
    public function on_activation( bool $network_wide ): void {
        $user_id = (int) get_current_user_id();

        $data = [
            'activator' => $user_id,
            'allowed'   => [],
        ];

        if ( is_multisite() && $network_wide ) {
            update_site_option( $this->optionKey, $data );
        } else {
            update_option( $this->optionKey, $data );
        }
    }

    /**
     * Runs the provided callback ONLY when permissions validate.
     */
    public function maybe_execute_callback(): void {
        $current_user = get_current_user_id();

        $data      = $this->get_data();
        $activator = (int) ( $data['activator'] ?? 0 );
        $allowed   = (array) ( $data['allowed'] ?? [] );

        // Fix deleted activator if needed.
        $activator = $this->fix_deleted_activator( $activator, $allowed );

        // If still no activator (rare), fall back to admins.
        if ( ! $activator ) {
            if ( user_can( $current_user, 'manage_options' ) ) {
                call_user_func( $this->onAllowedCallback );
            }
            return;
        }

        // Normal access control: activator or whitelisted.
        if (
            $current_user === $activator ||
            in_array( $current_user, $allowed, true )
        ) {
            call_user_func( $this->onAllowedCallback );
        }
    }

    /**
     * Public API — whitelist a user.
     */
    public function allow_user( int $user_id ): void {
        $data    = $this->get_data();
        $allowed = (array) ( $data['allowed'] ?? [] );

        if ( ! in_array( $user_id, $allowed, true ) ) {
            $allowed[]        = $user_id;
            $data['allowed']  = $allowed;
            $this->update_data( $data );
        }
    }

    /**
     * Public API — remove user from whitelist.
     */
    public function unallow_user( int $user_id ): void {
        $data    = $this->get_data();
        $allowed = (array) ( $data['allowed'] ?? [] );

        $allowed = array_filter(
            $allowed,
            static fn( $id ) => (int) $id !== $user_id
        );

        $data['allowed'] = array_values( $allowed );
        $this->update_data( $data );
    }

    /**
     * Read single stored option.
     */
    private function get_data(): array {
        if ( is_multisite() && $this->is_network_active() ) {
            return (array) get_site_option( $this->optionKey, [] );
        }

        return (array) get_option( $this->optionKey, [] );
    }

    /**
     * Write single stored option.
     */
    private function update_data( array $data ): void {
        if ( is_multisite() && $this->is_network_active() ) {
            update_site_option( $this->optionKey, $data );
        } else {
            update_option( $this->optionKey, $data );
        }
    }

    /**
     * Is this plugin network-activated?
     */
    private function is_network_active(): bool {
        if ( ! is_multisite() ) {
            return false;
        }

        if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active_for_network( plugin_basename( $this->pluginFile ) );
    }

    /**
     * If activator user is gone, reassign to first admin and persist.
     */
    private function fix_deleted_activator( int $activator, array $allowed ): int {
        if ( $activator > 0 && get_user_by( 'id', $activator ) ) {
            return $activator;
        }

        $admins = get_users( [
            'role'    => 'administrator',
            'fields'  => 'ID',
            'orderby' => 'ID',
            'order'   => 'ASC',
        ] );

        if ( empty( $admins ) ) {
            return 0;
        }

        $fallback = (int) $admins[0];

        $this->update_data( [
            'activator' => $fallback,
            'allowed'   => $allowed,
        ] );

        return $fallback;
    }
}
