<?php
namespace GiVendor\GiPlugin\Email\Notifications\Custom;

use GiVendor\GiPlugin\Email\Templates\TemplateParser;
use GiVendor\GiPlugin\Email\Templates\TemplateRenderer;
use GiVendor\GiPlugin\Email\EmailManager;
use GiVendor\GiPlugin\Utils\RfqLogger;

class NotificationManager {
    private static $instance = null;
    private $roles = ['user', 'supplier', 'admin'];
    private $tabs = ['user', 'supplier', 'admin', 'configuracion'];
    private $events = [
        'user' => [
            'solicitud_created' => 'Solicitud Creada',
            'cotizacion_submitted' => 'Cotización Recibida',
            'cotizacion_accepted' => 'Cotización Aceptada'
        ],
        'supplier' => [
            'solicitud_created' => 'Nueva solicitud',
            'cotizacion_submitted' => 'Cotización Enviada',
            'cotizacion_accepted' => 'Cotización Aceptada'
        ],
        'admin' => [
        'solicitud_created' => 'Solicitud created',
        'cotizacion_submitted' => 'Cotización submitted',
        'cotizacion_accepted' => 'Cotización accepted'
        ]
    ];

    private $cache;

    public function __construct() {
        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_action('wp_ajax_reset_notifications_for_role', [ $this, 'handle_reset_notifications' ]);
    }

    public function handle_reset_notifications(): void {
        check_ajax_referer('rfq_notifications_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
            return;
        }

        $role = sanitize_text_field($_POST['role'] ?? '');
        if (empty($role)) {
            wp_send_json_error('Rol no especificado');
            return;
        }

        $result = $this->reset_notifications_for_role($role);
        if ($result) {
            wp_send_json_success('Notificaciones reiniciadas correctamente');
        } else {
            wp_send_json_error('Error al reiniciar las notificaciones');
        }
    }

    public function reset_notifications_for_role(string $role): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $notifications = $this->get_notifications_for_role($role);
        if (empty($notifications)) {
            return true;
        }

        foreach ($notifications as $notification) {
            $this->delete_notification($notification->id);
        }

        // Limpiar la caché después de eliminar
        if (isset($this->cache) && method_exists($this->cache, 'clear_cache')) {
            $this->cache->clear_cache();
        }

        return true;
    }

    private function get_notifications_for_role(string $role): array {
        // Implementar la lógica para obtener notificaciones por rol
        return [];
    }

    private function delete_notification(int $id): bool {
        // Implementar la lógica para eliminar una notificación
        return true;
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function init(): void {
        add_action('admin_menu', [self::class, 'register_menu'], 20);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_init', [self::class, 'register_default_options']);
        
        // Extender KSES para elementos email-safe
        add_filter('wp_kses_allowed_html', [self::class, 'extend_kses_for_emails'], 10, 2);
        
        // Encolar scripts del editor para TinyMCE completo
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_editor_scripts']);
        
        // Inicializar manejador AJAX
        NotificationAjaxHandler::init();
    }

    public static function register_menu(): void {
        add_submenu_page(
            'rfq-manager',
            __('Notifications', 'rfq-manager-woocommerce'),
            __('Notifications', 'rfq-manager-woocommerce'),
            'manage_options',
            'rfq-notifications',
            [self::class, 'render_page']
        );
    }

    public static function register_default_options(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $instance = self::getInstance();
        foreach ($instance->roles as $role) {
            foreach ($instance->events[$role] as $event_key => $event_label) {
                // Registrar asunto por defecto con validación
                $subject_option = "rfq_notification_{$role}_{$event_key}_subject";
                if (!get_option($subject_option)) {
                    $default_subject = $instance->getDefaultSubject($role, $event_key);
                    update_option($subject_option, $default_subject);
                }

                // Registrar cuerpo por defecto con validación
                $body_option = "rfq_notification_{$role}_{$event_key}_body";
                if (!get_option($body_option)) {
                    $default_body = $instance->getDefaultMessage($role, $event_key);
                    update_option($body_option, $default_body);
                }
            }
        }
    }

    public static function register_settings(): void {
        foreach (self::getInstance()->roles as $role) {
            $group = "rfq_notifications_group_{$role}";
            
            foreach (self::getInstance()->events[$role] as $event_key => $event_label) {
                // Registrar campo de asunto
                register_setting(
                    $group,
                    "rfq_notification_{$role}_{$event_key}_subject",
                    [
                        'type' => 'string',
                        'sanitize_callback' => function($value) {
                            return sanitize_text_field($value);
                        },
                        'default' => self::getInstance()->getDefaultSubject($role, $event_key)
                    ]
                );

                // Registrar campo de cuerpo
                register_setting(
                    $group,
                    "rfq_notification_{$role}_{$event_key}_body",
                    [
                        'type' => 'string',
                        'sanitize_callback' => function($value) {
                            $allowed_html = array_merge(
                                wp_kses_allowed_html('post'),
                                [
                                    'style' => [
                                        'type' => true,
                                        'media' => true,
                                    ],
                                    'table' => [
                                        'class' => true,
                                        'style' => true,
                                    ],
                                    'tr' => [
                                        'class' => true,
                                        'style' => true,
                                    ],
                                    'td' => [
                                        'class' => true,
                                        'style' => true,
                                    ],
                                    'th' => [
                                        'class' => true,
                                        'style' => true,
                                    ],
                                ]
                            );
                            
                            return wp_kses($value, $allowed_html);
                        },
                        'default' => self::getInstance()->getDefaultMessage($role, $event_key)
                    ]
                );
            }
        }
        
        // Registrar configuración del pie legal
        register_setting(
            'rfq_legal_footer_group',
            'rfq_email_legal_footer',
            [
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
                'default' => ''
            ]
        );
        
        // Registrar configuración del BCC global
        register_setting(
            'rfq_legal_footer_group',
            'rfq_email_bcc_global',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ]
        );
    }

    /**
     * Extiende wp_kses para permitir atributos email-safe
     *
     * @since  0.2.0
     * @param  array  $tags    Tags permitidos
     * @param  string $context Contexto de sanitización
     * @return array  Tags extendidos para emails
     */
    public static function extend_kses_for_emails($tags, $context) {
        // Solo aplicar en contexto 'post' para no interferir con otros
        if ($context !== 'post') {
            return $tags;
        }
        
        // Atributos comunes para elementos email-safe
        $email_attrs = [
            'style' => true,
            'align' => true, 
            'valign' => true,
            'width' => true,
            'height' => true,
            'border' => true,
            'cellpadding' => true,
            'cellspacing' => true,
            'bgcolor' => true,
            'class' => true
        ];
        
        // Extender elementos de tabla para emails
        foreach (['table', 'tr', 'td', 'th', 'tbody', 'thead', 'tfoot'] as $table_tag) {
            $tags[$table_tag] = array_merge($tags[$table_tag] ?? [], $email_attrs);
        }
        
        // Extender imagen con atributos específicos
        $tags['img'] = array_merge($tags['img'] ?? [], [
            'src' => true,
            'alt' => true,
            'width' => true,
            'height' => true,
            'style' => true,
            'border' => true,
            'align' => true
        ]);
        
        // Extender enlaces con atributos adicionales
        $tags['a'] = array_merge($tags['a'] ?? [], [
            'href' => true,
            'target' => true,
            'title' => true,
            'style' => true,
            'class' => true
        ]);
        
        // Extender divs y spans para estructura email
        foreach (['div', 'span'] as $container_tag) {
            $tags[$container_tag] = array_merge($tags[$container_tag] ?? [], [
                'style' => true,
                'class' => true,
                'align' => true
            ]);
        }
        
        return $tags;
    }

    /**
     * Encola scripts necesarios para TinyMCE completo
     *
     * @since  0.2.0
     * @param  string $hook Hook de la página admin
     * @return void
     */
    public static function enqueue_editor_scripts($hook): void {
        // Solo cargar en nuestra página de notificaciones
        if (strpos($hook, 'rfq-notifications') === false) {
            return;
        }

        // Encolar scripts del editor clásico
        wp_enqueue_editor();
        wp_enqueue_script('editor');
        wp_enqueue_script('quicktags');
        wp_enqueue_script('jquery');
        
        // CSS adicional para mejor presentación
        wp_add_inline_style('wp-admin', '
            .rfq-templates-form .wp-editor-container {
                margin: 10px 0;
            }
            .rfq-templates-form .wp-editor-area {
                min-height: 300px !important;
            }
            /* Corregir problema de texto blanco en modo Code */
            .rfq-templates-form .wp-editor-wrap .wp-editor-area {
                color: #23282d !important;
                background: #fff !important;
            }
            .rfq-templates-form .wp-editor-wrap.html-active .wp-editor-area {
                color: #23282d !important;
                background: #f9f9f9 !important;
                font-family: Consolas, Monaco, monospace;
            }
            /* Evitar duplicación de editores */
            .rfq-templates-form .wp-editor-wrap .wp-editor-wrap {
                display: none;
            }
        ');
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Procesar la configuración del pie legal y BCC global
        if (isset($_POST['rfq_save_legal_footer']) && wp_verify_nonce($_POST['rfq_legal_footer_nonce'], 'rfq_save_legal_footer')) {
            if (current_user_can('manage_options')) {
                $legal_footer = wp_kses_post($_POST['rfq_email_legal_footer']);
                update_option('rfq_email_legal_footer', $legal_footer);
                
                $bcc_global = sanitize_text_field($_POST['rfq_email_bcc_global']);
                update_option('rfq_email_bcc_global', $bcc_global);
                
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . __('Configuración guardada correctamente.', 'rfq-manager-woocommerce') . '</p>';
                echo '</div>';
            }
        }

        // Procesar el reset si se ha enviado
        if (!empty($_POST['rfq_reset_role']) && check_admin_referer('rfq_reset_role', 'rfq_reset_nonce')) {
            $role = sanitize_text_field($_POST['rfq_reset_role']);
            
            // Eliminar todas las opciones para este rol
            foreach (self::getInstance()->events[$role] as $event_key => $event_label) {
                delete_option("rfq_notification_{$role}_{$event_key}_body");
                delete_option("rfq_notification_{$role}_{$event_key}_subject");
            }
            
            wp_redirect(admin_url("admin.php?page=rfq-notifications&tab={$role}&reset=1"));
            exit;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'user';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (!user_can_richedit()): ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php _e('Editor Visual Desactivado:', 'rfq-manager-woocommerce'); ?></strong>
                        <?php _e('Para usar el editor visual completo, desmarca "Desactivar el editor visual al escribir" en tu', 'rfq-manager-woocommerce'); ?>
                        <a href="<?php echo admin_url('profile.php'); ?>"><?php _e('perfil de usuario', 'rfq-manager-woocommerce'); ?></a>.
                    </p>
                </div>
            <?php endif; ?>
            
            <h2 class="nav-tab-wrapper">
                <?php foreach (self::getInstance()->roles as $role) : ?>
                    <a href="?page=rfq-notifications&tab=<?php echo esc_attr($role); ?>" 
                       class="nav-tab <?php echo $active_tab === $role ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html(ucfirst($role)); ?>
                    </a>
                <?php endforeach; ?>
                <a href="?page=rfq-notifications&tab=configuracion" 
                   class="nav-tab <?php echo $active_tab === 'configuracion' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Configuración', 'rfq-manager-woocommerce'); ?>
                </a>
            </h2>

            <div class="rfq-templates-container">
                <?php if ($active_tab === 'configuracion'): ?>
                    <div class="rfq-config-form">
                        <form method="post" action="">
                            <?php wp_nonce_field('rfq_save_legal_footer', 'rfq_legal_footer_nonce'); ?>
                            <h3><?php _e('Configuración del Pie Legal de Emails', 'rfq-manager-woocommerce'); ?></h3>
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row">
                                            <label for="rfq_email_legal_footer">
                                                <?php _e('Pie Legal de Emails', 'rfq-manager-woocommerce'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <div style="max-width: 100%;">
                                                <?php
                                                $legal_footer_content = get_option('rfq_email_legal_footer', '');
                                                wp_editor($legal_footer_content, 'rfq_email_legal_footer', [
                                                    'media_buttons' => false,
                                                    'textarea_name' => 'rfq_email_legal_footer',
                                                    'textarea_rows' => 10,
                                                    'teeny' => true,
                                                    'editor_css' => '<style>#wp-rfq_email_legal_footer-editor-container .wp-editor-area{height:200px;}</style>',
                                                    'quicktags' => [
                                                        'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code'
                                                    ]
                                                ]);
                                                ?>
                                            </div>
                                            <p class="description">
                                                <?php _e('Este texto aparecerá al final de todos los emails de notificación del sistema RFQ. Puedes usar HTML básico para formato.', 'rfq-manager-woocommerce'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="rfq_email_bcc_global">
                                                <?php _e('BCC Global (separar por comas)', 'rfq-manager-woocommerce'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="rfq_email_bcc_global" 
                                                   name="rfq_email_bcc_global" 
                                                   value="<?php echo esc_attr(get_option('rfq_email_bcc_global', '')); ?>" 
                                                   class="regular-text" 
                                                   placeholder="ejemplo1@correo.com, ejemplo2@correo.com" />
                                            <p class="description">
                                                <?php _e('Se añadirá como BCC a <strong>todas</strong> las notificaciones del sistema RFQ. Separar múltiples correos por comas.', 'rfq-manager-woocommerce'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="submit" name="rfq_save_legal_footer" class="button button-primary">
                                <?php _e('Guardar Configuración', 'rfq-manager-woocommerce'); ?>
                            </button>
                        </form>
                    </div>
                    <div class="rfq-config-info">
                        <div class="rfq-config-help">
                            <h4><?php _e('Información', 'rfq-manager-woocommerce'); ?></h4>
                            <p><?php _e('Configuraciones que se aplican a todas las notificaciones por email:', 'rfq-manager-woocommerce'); ?></p>
                            <ul>
                                <li>• <strong><?php _e('Pie Legal:', 'rfq-manager-woocommerce'); ?></strong> <?php _e('Se añade al final de todos los emails', 'rfq-manager-woocommerce'); ?></li>
                                <li>• <strong><?php _e('BCC Global:', 'rfq-manager-woocommerce'); ?></strong> <?php _e('Recibe copia oculta de todas las notificaciones', 'rfq-manager-woocommerce'); ?></li>
                            </ul>
                            <p><strong><?php _e('Tipos de notificaciones afectadas:', 'rfq-manager-woocommerce'); ?></strong></p>
                            <ul>
                                <li>• <?php _e('Notificaciones a usuarios', 'rfq-manager-woocommerce'); ?></li>
                                <li>• <?php _e('Notificaciones a proveedores', 'rfq-manager-woocommerce'); ?></li>
                                <li>• <?php _e('Notificaciones a administradores', 'rfq-manager-woocommerce'); ?></li>
                            </ul>
                            <p><strong><?php _e('Ejemplo BCC Global:', 'rfq-manager-woocommerce'); ?></strong></p>
                            <div style="background: #f9f9f9; padding: 10px; border-left: 4px solid #0073aa; margin: 10px 0;">
                                <code>admin@empresa.com, auditoria@empresa.com</code>
                            </div>
                            <p><strong><?php _e('Ejemplo Pie Legal:', 'rfq-manager-woocommerce'); ?></strong></p>
                            <div style="background: #f9f9f9; padding: 10px; border-left: 4px solid #0073aa; margin: 10px 0;">
                                <code>
                                    &lt;p&gt;&lt;small&gt;Este email fue enviado automáticamente por el sistema TCD Manager.&lt;br&gt;
                                    Para dudas contacta: &lt;a href="mailto:soporte@ejemplo.com"&gt;soporte@ejemplo.com&lt;/a&gt;&lt;/small&gt;&lt;/p&gt;
                                </code>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                <div class="rfq-templates-form">
            <form method="post" action="options.php">
                        <?php 
                        $group = "rfq_notifications_group_{$active_tab}";
                        settings_fields($group);
                        ?>
                <h3><?php echo esc_html(ucfirst($active_tab)); ?> <?php _e('Templates', 'rfq-manager-woocommerce'); ?></h3>
                <table class="form-table" role="presentation">
                    <tbody>
                                <?php foreach (self::getInstance()->events[$active_tab] as $event_key => $event_label) : ?>
                            <tr>
                                <th scope="row"><?php echo esc_html($event_label); ?></th>
                                <td>
                                    <p>
                                        <label for="<?php echo esc_attr("rfq_notification_{$active_tab}_{$event_key}_subject"); ?>">
                                            <?php _e('Subject', 'rfq-manager-woocommerce'); ?>
                                        </label><br>
                                        <input type="text" 
                                               id="<?php echo esc_attr("rfq_notification_{$active_tab}_{$event_key}_subject"); ?>"
                                               name="<?php echo esc_attr("rfq_notification_{$active_tab}_{$event_key}_subject"); ?>"
                                               value="<?php echo esc_attr(get_option("rfq_notification_{$active_tab}_{$event_key}_subject")); ?>"
                                               class="regular-text">
                                    </p>
                                    <p>
                                        <label for="<?php echo esc_attr("rfq_notification_{$active_tab}_{$event_key}_body"); ?>">
                                            <?php _e('Message', 'rfq-manager-woocommerce'); ?>
                                        </label><br>
                                                <?php 
                                                $default_body = self::getInstance()->getDefaultMessage($active_tab, $event_key);
                                                $saved_body = get_option("rfq_notification_{$active_tab}_{$event_key}_body", '');
                                                $value = $saved_body !== '' ? $saved_body : $default_body;
                                                $field_name = "rfq_notification_{$active_tab}_{$event_key}_body";
                                                $editor_id = "rfq_tpl_{$active_tab}_{$event_key}_body";
                                                
                                                wp_editor($value, $editor_id, [
                                                    'textarea_name' => $field_name,
                                                    'textarea_rows' => 16,
                                                    'teeny' => false,  // Activar barra completa TinyMCE
                                                    'media_buttons' => false,  // No subir medios
                                                    'drag_drop_upload' => false,
                                                    'wpautop' => true,  // Ayuda para párrafos automáticos
                                                    'quicktags' => true,  // Pestaña "Texto" (HTML)
                                                    'editor_css' => '<style>#wp-' . $editor_id . '-editor-container .wp-editor-area{min-height:300px;}</style>',
                                                    'tinymce' => [
                                                        'toolbar1' => 'formatselect,bold,italic,underline,blockquote,alignleft,aligncenter,alignright,bullist,numlist,link,unlink,removeformat,code',
                                                        'toolbar2' => 'table,outdent,indent',
                                                        'block_formats' => 'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4',
                                                        'paste_as_text' => true,  // Evita basura al pegar
                                                        'menubar' => false,
                                                        'branding' => false,
                                                        'setup' => 'function(editor) { editor.on("init", function() { console.log("TinyMCE inicializado para: " + editor.id); }); }'
                                                    ]
                                                ]);
                                                ?>
                                        </p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                        <?php submit_button('Guardar cambios'); ?>
                    </form>

                    <!-- Formulario para resetear todas las plantillas del rol -->
                    <form method="post" action="">
                        <?php wp_nonce_field('rfq_reset_role', 'rfq_reset_nonce'); ?>
                        <input type="hidden" name="rfq_reset_role" value="<?php echo esc_attr($active_tab); ?>">
                        <button type="submit" class="button button-secondary">
                            <?php echo esc_html(sprintf(__('Resetear todas las plantillas de %s', 'rfq-manager-woocommerce'), ucfirst($active_tab))); ?>
                        </button>
            </form>
        </div>

                <div class="rfq-placeholders-sidebar">
                    <div class="rfq-placeholders-docs">
                        <h4><?php _e('Placeholders Disponibles', 'rfq-manager-woocommerce'); ?></h4>
                        <div class="rfq-placeholders-list">
                            <?php
                            $placeholders = self::getInstance()->getAvailablePlaceholders($active_tab);
                            foreach ($placeholders as $placeholder => $description) {
                                echo '<div class="rfq-placeholder-item">';
                                echo '<code>' . esc_html($placeholder) . '</code>';
                                echo '<span class="description">' . esc_html($description) . '</span>';
                                echo '</div>';
                            }
                            ?>
                        </div>
            </div>
        </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .rfq-templates-container {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 20px;
                margin-top: 20px;
            }
            .rfq-templates-form, .rfq-config-form {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            .rfq-placeholders-sidebar, .rfq-config-info {
                position: sticky;
                top: 32px;
            }
            .rfq-placeholders-docs, .rfq-config-help {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 15px;
                border-radius: 4px;
            }
            .rfq-config-help ul {
                margin-left: 20px;
            }
            .rfq-config-help code {
                display: block;
                font-size: 12px;
                line-height: 1.4;
            }
            .rfq-placeholders-list {
                display: flex;
                flex-direction: column;
                gap: 10px;
                margin-top: 10px;
            }
            .rfq-placeholder-item {
                background: #f8f9fa;
                padding: 10px;
                border-radius: 3px;
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            .rfq-placeholder-item code {
                background: #e9ecef;
                padding: 3px 6px;
                border-radius: 3px;
                font-size: 13px;
                cursor: pointer;
            }
            .rfq-placeholder-item .description {
                color: #666;
                font-size: 12px;
            }
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.4);
            }
            .modal-content {
                background-color: #fefefe;
                margin: 15% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 600px;
            }
            .close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            .close:hover {
                color: black;
            }
        </style>

        <script>
        jQuery(function($) {
            // Los editores TinyMCE ya están inicializados por wp_editor()
            // Solo necesitamos manejar eventos y funcionalidad adicional

            // Preview template
            $('.preview-template').click(function(e) {
                e.preventDefault();
                var role = $(this).data('role');
                var event = $(this).data('event');
                var template = $('#rfq_notification_' + role + '_' + event + '_body').val();
                
                $.post(ajaxurl, {
                    action: 'preview_template',
                    role: role,
                    event: event,
                    template: template,
                    nonce: '<?php echo wp_create_nonce('preview_template'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#preview-content').html(response.data);
                        $('#preview-modal').show();
                    }
                });
            });

            // View versions
            $('.view-versions').click(function(e) {
                e.preventDefault();
                var role = $(this).data('role');
                var event = $(this).data('event');
                
                $.post(ajaxurl, {
                    action: 'get_template_versions',
                    role: role,
                    event: event,
                    nonce: '<?php echo wp_create_nonce('get_template_versions'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#versions-content').html(response.data);
                        $('#versions-modal').show();
                    }
                });
            });

            // Close modals
            $('.close').click(function() {
                $('.modal').hide();
            });

            $(window).click(function(e) {
                if ($(e.target).hasClass('modal')) {
                    $('.modal').hide();
                }
            });

            // Hacer los placeholders clickeables
            $('.rfq-placeholder-item code').click(function() {
                var placeholder = $(this).text();
                var activeEditor = null;
                
                // Intentar con TinyMCE activo primero
                if (typeof tinymce !== 'undefined') {
                    activeEditor = tinymce.activeEditor;
                    if (activeEditor && !activeEditor.isHidden()) {
                        activeEditor.execCommand('mceInsertContent', false, placeholder);
                        return;
                    }
                }
                
                // Fallback: buscar textarea activo o con foco
                var $activeTextarea = $('textarea:focus');
                if ($activeTextarea.length === 0) {
                    $activeTextarea = $('.rfq-templates-form:visible textarea').first();
                }
                
                if ($activeTextarea.length > 0) {
                    var textarea = $activeTextarea[0];
                    var start = textarea.selectionStart;
                    var end = textarea.selectionEnd;
                    var text = textarea.value;
                    
                    textarea.value = text.substring(0, start) + placeholder + text.substring(end);
                    textarea.selectionStart = textarea.selectionEnd = start + placeholder.length;
                    textarea.focus();
                }
            });
        });
        </script>

        <!-- Modal para preview -->
        <div id="preview-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2><?php _e('Template Preview', 'rfq-manager-woocommerce'); ?></h2>
                <div id="preview-content"></div>
            </div>
        </div>

        <!-- Modal para versiones -->
        <div id="versions-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2><?php _e('Template Versions', 'rfq-manager-woocommerce'); ?></h2>
                <div id="versions-content"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Obtiene la plantilla actual para un rol y evento específicos
     */
    public function getCurrentTemplate(string $role, string $event): string {
        $template = get_option("rfq_notification_{$role}_{$event}_body", '');
        
        if (empty($template)) {
            return $this->getDefaultMessage($role, $event);
        }
        
        return $template;
    }

    /**
     * Obtiene el asunto actual para un rol y evento específicos
     */
    public function getCurrentSubject(string $role, string $event): string {
        $subject = get_option("rfq_notification_{$role}_{$event}_subject", '');
        
        if (empty($subject)) {
            return $this->getDefaultSubject($role, $event);
        }
        
        return $subject;
    }

    /**
     * Previsualiza una plantilla con datos de muestra
     */
    public function previewTemplate(string $role, string $event, string $template): string {
        $sample_data = $this->getSampleData($role, $event);
        return TemplateParser::render($template, $sample_data);
    }

    private function getDefaultSubject(string $role, string $event): string {
        $defaults = [
            'user' => [
                'solicitud_created' => 'Nueva solicitud de cotización creada',
                'cotizacion_submitted' => 'Cotización recibida para tu solicitud',
                'cotizacion_accepted' => 'Tu cotización ha sido aceptada'
            ],
            'supplier' => [
                'solicitud_created' => 'Nueva solicitud de cotización disponible',
                'cotizacion_submitted' => 'Cotización enviada correctamente',
                'cotizacion_accepted' => '¡Tu cotización ha sido aceptada!'
            ],
            'admin' => [
                'solicitud_created' => 'Nueva solicitud de cotización recibida',
                'cotizacion_submitted' => 'Nueva cotización enviada',
                'cotizacion_accepted' => 'Cotización aceptada por el cliente'
            ]
        ];

        return $defaults[$role][$event] ?? '[ no subject ]';
    }

    private function getDefaultMessage($role, $event) {
        $defaults = [
            'user' => [
                'solicitud_created' => 'Hola {user_name},<br><br>Tu solicitud de cotización ha sido creada exitosamente.<br><br>Detalles de la solicitud:<br>ID: {request_id}<br>Título: {request_title}<br>Fecha de expiración: {request_expiry}<br><br>Puedes ver el estado de tu solicitud en: {request_link}',
                'cotizacion_received' => 'Hola {user_name},<br><br>Has recibido una nueva cotización para tu solicitud {request_title}.<br><br>Detalles de la cotización:<br>ID: {quote_id}<br>Proveedor: {supplier_name}<br>Monto total: {quote_amount}<br><br>Puedes ver la cotización en: {quote_link}',
                'cotizacion_accepted' => 'Hola {user_name},<br><br>Has aceptado una cotización para tu solicitud {request_title}.<br><br>Detalles de la cotización aceptada:<br>ID: {quote_id}<br>Proveedor: {supplier_name}<br>Monto total: {quote_amount}<br><br>El proveedor se pondrá en contacto contigo para coordinar los detalles.',
                'status_changed' => 'Hola {user_name},<br><br>El estado de tu solicitud {request_title} ha cambiado de {status_old} a {status_new}.<br><br>Puedes ver los detalles en: {request_link}'
            ],
            'supplier' => [
                'solicitud_created' => 'Hola {supplier_name},<br><br>Hay una nueva solicitud de cotización disponible.<br><br>Detalles de la solicitud:<br>ID: {request_id}<br>Título: {request_title}<br>Cliente: {customer_name}<br>Fecha de expiración: {request_expiry}<br><br>Productos solicitados:<br>{productos}<br><br>Puedes enviar tu cotización en: {request_link}',
                'cotizacion_submitted' => 'Hola {supplier_name},<br><br>Has enviado una cotización para la solicitud {request_title}.<br><br>Detalles de la cotización:<br>ID: {quote_id}<br>Cliente: {customer_name}<br>Monto total: {quote_amount}<br><br>Productos cotizados:<br>{productos_cotizados}<br><br>Puedes ver tu cotización en: {quote_link}',
                'cotizacion_accepted' => 'Hola {supplier_name},<br><br>¡Felicidades! Tu cotización para la solicitud {request_title} ha sido aceptada.<br><br>Detalles de la cotización aceptada:<br>ID: {quote_id}<br>Cliente: {customer_name}<br>Monto total: {quote_amount}<br><br>El cliente se pondrá en contacto contigo para coordinar los detalles.'
            ],
            'admin' => [
                'solicitud_created' => 'Hola Administrador,<br><br>Se ha creado una nueva solicitud de cotización.<br><br>Detalles de la solicitud:<br>ID: {request_id}<br>Título: {request_title}<br>Cliente: {user_name}<br>Estado: {request_status}<br>Fecha de expiración: {request_expiry}<br><br>Puedes ver los detalles en: {request_link}',
                'cotizacion_submitted' => 'Hola Administrador,<br><br>Se ha enviado una nueva cotización.<br><br>Detalles de la cotización:<br>ID: {quote_id}<br>Solicitud: {request_title}<br>Proveedor: {supplier_name}<br>Cliente: {user_name}<br>Monto total: {quote_amount}<br><br>Puedes ver los detalles en: {quote_link}',
                'cotizacion_accepted' => 'Hola Administrador,<br><br>Una cotización ha sido aceptada.<br><br>Detalles:<br>ID de cotización: {quote_id}<br>Solicitud: {request_title}<br>Proveedor: {supplier_name}<br>Cliente: {user_name}<br>Monto total: {quote_amount}<br><br>Puedes ver los detalles en: {quote_link}'
            ]
        ];

        return $defaults[$role][$event] ?? '';
    }

    private function getSampleData($role, $event) {
        $sample = [
            'user' => [
                'nombre' => 'Juan Pérez',
                'numero_solicitud' => 'RFQ-2024-001',
                'fecha' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
                'productos' => "Producto 1 x 2\nProducto 2 x 1"
            ],
            'supplier' => [
                'nombre' => 'Proveedor XYZ',
                'numero_solicitud' => 'RFQ-2024-001',
                'fecha' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
                'productos' => "Producto 1 x 2\nProducto 2 x 1"
            ],
            'admin' => [
                'nombre' => 'Administrador',
                'numero_solicitud' => 'RFQ-2024-001',
                'fecha' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
                'productos' => "Producto 1 x 2\nProducto 2 x 1"
            ]
        ];
        return $sample[$role] ?? [];
        }

    /**
     * Obtiene los placeholders disponibles para un rol específico
     */
    private function getAvailablePlaceholders(string $role): array {
        $placeholders = [
            'common' => [
                '{site_name}' => __('Nombre del sitio', 'rfq-manager-woocommerce'),
                '{site_url}' => __('URL del sitio', 'rfq-manager-woocommerce'),
                '{admin_email}' => __('Email del administrador', 'rfq-manager-woocommerce'),
                '{fecha}' => __('Fecha y hora actual', 'rfq-manager-woocommerce'),
                '{current_year}' => __('Año actual', 'rfq-manager-woocommerce'),
                '{user_dashboard_link}' => __('Enlace al panel del usuario (Mi Cuenta)', 'rfq-manager-woocommerce'),
                '{supplier_dashboard_link}' => __('Enlace al panel del proveedor', 'rfq-manager-woocommerce'),
                '{admin_dashboard_link}' => __('Enlace al panel de administración de RFQ', 'rfq-manager-woocommerce'),
            ],
            'request' => [
                '{request_id}' => __('ID de la solicitud', 'rfq-manager-woocommerce'),
                '{request_uuid}' => __('UUID de la solicitud', 'rfq-manager-woocommerce'),
                '{request_title}' => __('Título de la solicitud', 'rfq-manager-woocommerce'),
                '{request_description}' => __('Descripción de la solicitud', 'rfq-manager-woocommerce'),
                '{request_status}' => __('Estado de la solicitud', 'rfq-manager-woocommerce'),
                '{request_expiry}' => __('Fecha de expiración de la solicitud', 'rfq-manager-woocommerce'),
                '{request_link}' => __('Enlace a la solicitud', 'rfq-manager-woocommerce'),
                '{quotes_count}' => __('Número de cotizaciones recibidas para la solicitud', 'rfq-manager-woocommerce'),
            ],
            'quote' => [
                '{quote_id}' => __('ID de la cotización', 'rfq-manager-woocommerce'),
                '{quote_title}' => __('Título de la cotización', 'rfq-manager-woocommerce'),
                '{quote_amount}' => __('Monto de la cotización', 'rfq-manager-woocommerce'),
                '{total}' => __('Monto de la cotización (alias de {quote_amount})', 'rfq-manager-woocommerce'),
                '{quote_date}' => __('Fecha de la cotización', 'rfq-manager-woocommerce'),
                '{quote_link}' => __('Enlace a la cotización', 'rfq-manager-woocommerce'),
            ],
            'supplier_specific' => [
                '{supplier_name}' => __('Nombre del proveedor', 'rfq-manager-woocommerce'),
                '{supplier_email}' => __('Email del proveedor', 'rfq-manager-woocommerce'),
            ],
            'user_specific' => [
                '{user_name}' => __('Nombre del usuario/cliente', 'rfq-manager-woocommerce'),
                '{user_email}' => __('Email del usuario/cliente', 'rfq-manager-woocommerce'),
                '{first_name}' => __('Nombre de pila del usuario', 'rfq-manager-woocommerce'),
                '{last_name}' => __('Apellidos del usuario', 'rfq-manager-woocommerce'),
                '{new_request_link}' => __('Enlace para crear nueva solicitud', 'rfq-manager-woocommerce'),
                '{history_link}' => __('Enlace al historial de solicitudes', 'rfq-manager-woocommerce'),
            ],
            'admin_specific' => [
                '{admin_name}' => __('Nombre del administrador (destinatario)', 'rfq-manager-woocommerce'),
            ],
            'cross_info' => [
                '{customer_name}' => __('Nombre del cliente (autor de la solicitud)', 'rfq-manager-woocommerce'),
                '{customer_email}' => __('Email del cliente (autor de la solicitud)', 'rfq-manager-woocommerce'),
                '{customer_first_name}' => __('Nombre de pila del cliente', 'rfq-manager-woocommerce'),
                '{customer_last_name}' => __('Apellidos del cliente', 'rfq-manager-woocommerce'),
            ],
            'status_change' => [
                '{status_new}' => __('Nuevo estado de la solicitud', 'rfq-manager-woocommerce'),
                '{status_old}' => __('Antiguo estado de la solicitud', 'rfq-manager-woocommerce'),
                '{porcentaje_ahorro}' => __('Porcentaje de ahorro (si aplica)', 'rfq-manager-woocommerce'),
            ],
            'item_lists' => [
                 '{items}' => __('Lista de productos/items (genérico)', 'rfq-manager-woocommerce'),
            ]
        ];

        $role_placeholders = $placeholders['common'];

        switch ($role) {
            case 'supplier':
                $role_placeholders = array_merge(
                    $role_placeholders,
                    $placeholders['request'],
                    $placeholders['quote'],
                    $placeholders['supplier_specific'],
                    ['{user_name}' => __('Nombre del cliente (que creó la solicitud)', 'rfq-manager-woocommerce')],
                    ['{customer_name}' => $placeholders['cross_info']['{customer_name}']],
                    ['{customer_email}' => $placeholders['cross_info']['{customer_email}']],
                    $placeholders['item_lists']
                );
                $role_placeholders['{quote_link}'] = __('Enlace para gestionar/ver su cotización', 'rfq-manager-woocommerce');
                $role_placeholders['{request_link}'] = __('Enlace para cotizar la solicitud', 'rfq-manager-woocommerce');
                break;
            case 'user':
                $role_placeholders = array_merge(
                    $role_placeholders,
                    $placeholders['request'],
                    $placeholders['quote'],
                    $placeholders['user_specific'],
                    ['{supplier_name}' => $placeholders['supplier_specific']['{supplier_name}']],
                    ['{supplier_email}' => $placeholders['supplier_specific']['{supplier_email}']],
                    $placeholders['status_change'],
                    $placeholders['item_lists']
                );
                break;
            case 'admin':
                $role_placeholders = array_merge(
                    $role_placeholders,
                    $placeholders['request'],
                    $placeholders['quote'],
                    $placeholders['admin_specific'],
                    ['{user_name}' => __('Nombre del cliente (que creó la solicitud)', 'rfq-manager-woocommerce')],
                    ['{user_email}' => $placeholders['user_specific']['{user_email}']],
                    ['{supplier_name}' => $placeholders['supplier_specific']['{supplier_name}']],
                    ['{supplier_email}' => $placeholders['supplier_specific']['{supplier_email}']],
                    $placeholders['cross_info'],
                    $placeholders['status_change'],
                    $placeholders['item_lists']
                );
                break;
        }
        return array_unique($role_placeholders);
    }

    public function replacePlaceholders(string $content, array $data, string $role): string {
        $placeholders = $this->getAvailablePlaceholders($role);
        $replacements = [];

        // Reemplazar placeholders específicos del rol
        foreach ($placeholders as $placeholder => $description) {
            $key = trim($placeholder, '{}');
            if (isset($data[$key])) {
                $replacements[$placeholder] = $data[$key];
            }
        }

        // Manejar condicionales
        $content = preg_replace_callback('/{if\s+([^}]+)}(.*?){\/if}/s', function($matches) use ($data) {
            $condition = trim($matches[1]);
            $content = $matches[2];
            
            if (isset($data[$condition]) && !empty($data[$condition])) {
                return $content;
            }
            return '';
        }, $content);

        // Reemplazar todos los placeholders
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        return $content;
    }

    /**
     * Devuelve el contenido del header.
     */
    protected function loadHeader(): string {
        $header_path = RFQ_MANAGER_WOO_PLUGIN_DIR . '/src/Email/Templates/header-notification.php';
        if (!file_exists($header_path)) {
            error_log('[RFQ-ERROR] No se encontró el archivo de header: ' . $header_path);
            return '';
        }
        ob_start();
        include $header_path;
        return ob_get_clean();
    }

    /**
     * Devuelve el contenido del footer.
     */
    protected function loadFooter(): string {
        $footer_path = RFQ_MANAGER_WOO_PLUGIN_DIR . '/src/Email/Templates/footer-notification.php';
        if (!file_exists($footer_path)) {
            error_log('[RFQ-ERROR] No se encontró el archivo de footer: ' . $footer_path);
            return '';
        }
        ob_start();
        include $footer_path;
        return ob_get_clean();
    }

    /**
     * Devuelve el body personalizado (si existe) o el default.
     */
    public function getBodyTemplate(string $role, string $event): string {
        $custom = get_option("rfq_notification_{$role}_{$event}_body", '');
        return $custom !== '' ? $custom : $this->getDefaultMessage($role, $event);
    }
    
    /**
     * Resuelve first_name y last_name para un usuario
     *
     * @since  0.1.0
     * @param  int|WC_Order $user_or_order ID del usuario o objeto orden
     * @return array ['first_name' => string, 'last_name' => string]
     */
    public static function resolve_user_names($user_or_order): array {
        $first_name = '';
        $last_name = '';
        
        // Caso 1: Si es una orden, intentar obtener datos del customer
        if (is_object($user_or_order) && method_exists($user_or_order, 'get_billing_first_name')) {
            $first_name = $user_or_order->get_billing_first_name();
            $last_name = $user_or_order->get_billing_last_name();
            
            if (!empty($first_name) && !empty($last_name)) {
                return ['first_name' => $first_name, 'last_name' => $last_name];
            }
            
            // Fallback al customer de la orden
            $user_id = $user_or_order->get_customer_id();
            if ($user_id) {
                return self::resolve_user_names($user_id);
            }
        }
        
        // Caso 2: ID de usuario - buscar en WP_User meta
        if (is_numeric($user_or_order)) {
            $user_id = (int)$user_or_order;
            $first_name = get_user_meta($user_id, 'first_name', true);
            $last_name = get_user_meta($user_id, 'last_name', true);
            
            if (!empty($first_name) && !empty($last_name)) {
                return ['first_name' => $first_name, 'last_name' => $last_name];
            }
            
            // Caso 3: Último recurso - partir display_name
            $user = get_userdata($user_id);
            if ($user && !empty($user->display_name)) {
                $name_parts = explode(' ', trim($user->display_name), 2);
                $first_name = $name_parts[0] ?? '';
                $last_name = $name_parts[1] ?? '';
                
                return ['first_name' => $first_name, 'last_name' => $last_name];
            }
        }
        
        // Fallback: valores vacíos
        return ['first_name' => '', 'last_name' => ''];
    }

    /**
     * Ensambla header + body + footer en un solo string listo para parsear.
     */
    public function getCompiledTemplate(string $role, string $event): string {
        return $this->loadHeader() . $this->getBodyTemplate($role, $event) . $this->loadFooter();
    }

    /**
     * Pipeline consolidado: prepara el mensaje completo para envío
     *
     * @since  0.2.0
     * @param  string $event   Clave del evento (ej: 'user_solicitud_created')
     * @param  array  $context Array con datos del contexto
     * @return array Array con 'subject', 'html', 'text', 'headers'
     */
    public static function prepare_message(string $event, array $context): array {
        // 1. Resolver rol desde el evento
        $role = self::extract_role_from_event($event);
        $event_key = self::extract_event_key_from_event($event);
        
        if (!$role || !$event_key) {
            RfqLogger::warn('[prepare_message] Evento inválido: ' . $event, ['context' => $context]);
            return self::get_empty_message();
        }

        $instance = self::getInstance();
        
        // 2. Resolver plantilla actual (subject y body)
        $subject_template = $instance->getCurrentSubject($role, $event_key);
        $body_template = $instance->getBodyTemplate($role, $event_key);
        
        // Aplicar filtros de personalización
        $subject_template = apply_filters('rfq_prepare_message_subject', $subject_template, $event, $context);
        $body_template = apply_filters('rfq_prepare_message_body', $body_template, $event, $context);
        
        // 3. Construir variables (reusar helpers existentes + mergear context)
        $base_vars = self::build_base_variables($context, $role);
        $final_vars = array_merge($base_vars, $context);
        
        // Aplicar filtro de variables
        $final_vars = apply_filters('rfq_prepare_message_vars', $final_vars, $event, $context);
        
        // 4. Render con TemplateRenderer (HTML y texto)
        $legal_footer = get_option('rfq_email_legal_footer', '');
        $legal_footer = wp_kses_post($legal_footer);
        
        $html = TemplateRenderer::render_html(
            $body_template,
            $final_vars,
            $legal_footer,
            ['notification_type' => $event] + $context
        );
        
        $subject = TemplateParser::render($subject_template, $final_vars);
        $text = TemplateRenderer::render_text($html);
        
        // 5. Headers con EmailManager
        $extra_headers = $context['extra_headers'] ?? [];
        $headers = EmailManager::build_headers($extra_headers);
        
        // 6. Logging
        RfqLogger::debug('[prepare_message] listo', [
            'event' => $event,
            'vars_count' => count($final_vars),
            'has_legal_footer' => !empty($legal_footer)
        ]);
        
        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
            'headers' => $headers
        ];
    }
    
    /**
     * Extrae el rol desde la clave del evento
     * 
     * @param  string $event Ej: 'user_solicitud_created', 'admin_cotizacion_submitted'
     * @return string|null
     */
    private static function extract_role_from_event(string $event): ?string {
        $parts = explode('_', $event, 2);
        $role = $parts[0] ?? null;
        
        if (in_array($role, ['user', 'supplier', 'admin'])) {
            return $role;
        }
        
        return null;
    }
    
    /**
     * Extrae la clave del evento desde la clave completa
     * 
     * @param  string $event Ej: 'user_solicitud_created' -> 'solicitud_created'
     * @return string|null
     */
    private static function extract_event_key_from_event(string $event): ?string {
        $parts = explode('_', $event, 2);
        return $parts[1] ?? null;
    }
    
    /**
     * Construye variables base usando helpers existentes
     * 
     * @param  array  $context Array con datos del contexto
     * @param  string $role    Rol del destinatario
     * @return array Variables base normalizadas
     */
    private static function build_base_variables(array $context, string $role): array {
        $vars = [
            'site_name' => get_bloginfo('name'),
            'site_url' => get_bloginfo('url'),
            'admin_email' => get_option('admin_email'),
            'fecha' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
            'current_year' => date('Y')
        ];
        
        // Resolver first_name y last_name si hay usuario en contexto
        if (isset($context['user_id'])) {
            $names = self::resolve_user_names($context['user_id']);
            $vars['first_name'] = $names['first_name'];
            $vars['last_name'] = $names['last_name'];
        }
        
        // Resolver nombres de usuario creador si hay solicitud
        if (isset($context['solicitud_id'])) {
            $author_id = get_post_field('post_author', $context['solicitud_id']);
            if ($author_id) {
                $author = get_userdata($author_id);
                $author_names = self::resolve_user_names($author_id);
                $vars['customer_name'] = $author ? $author->display_name : '';
                $vars['customer_email'] = $author ? $author->user_email : '';
                $vars['customer_first_name'] = $author_names['first_name'];
                $vars['customer_last_name'] = $author_names['last_name'];
                
                // Para usuario también es user_name
                if ($role === 'user') {
                    $vars['user_name'] = $vars['customer_name'];
                    $vars['user_email'] = $vars['customer_email'];
                }
            }
        }
        
        // Resolver datos de proveedor si hay supplier_id
        if (isset($context['supplier_id'])) {
            $supplier = get_userdata($context['supplier_id']);
            $supplier_names = self::resolve_user_names($context['supplier_id']);
            $vars['supplier_name'] = $supplier ? $supplier->display_name : '';
            $vars['supplier_email'] = $supplier ? $supplier->user_email : '';
            $vars['supplier_first_name'] = $supplier_names['first_name'];
            $vars['supplier_last_name'] = $supplier_names['last_name'];
        }
        
        return $vars;
    }
    
    /**
     * Retorna un mensaje vacío en caso de error
     */
    private static function get_empty_message(): array {
        return [
            'subject' => '[Error] Mensaje no disponible',
            'html' => '<p>Error al generar el mensaje.</p>',
            'text' => 'Error al generar el mensaje.',
            'headers' => ['Content-Type: text/html; charset=UTF-8']
        ];
    }
}

/**
 * HOOKS Y FILTROS NUEVOS (Fase 3)
 * 
 * @since 0.2.0
 * 
 * Filtros para personalizar la preparación del mensaje:
 * 
 * - 'rfq_prepare_message_subject': Personalizar el asunto
 *   @param string $subject_template El asunto actual
 *   @param string $event           Clave del evento
 *   @param array  $context         Contexto del mensaje
 *   @return string Asunto personalizado
 * 
 * - 'rfq_prepare_message_body': Personalizar el cuerpo del mensaje
 *   @param string $body_template Cuerpo del mensaje actual
 *   @param string $event         Clave del evento
 *   @param array  $context       Contexto del mensaje
 *   @return string Cuerpo personalizado
 * 
 * - 'rfq_prepare_message_vars': Personalizar las variables del template
 *   @param array  $variables Array de variables para el template
 *   @param string $event     Clave del evento
 *   @param array  $context   Contexto del mensaje
 *   @return array Variables personalizadas
 * 
 * Filtros para personalizar el envío:
 * 
 * - 'rfq_before_send_email': Modificar destinatarios antes del envío
 *   @param array  $recipients Lista de destinatarios
 *   @param string $subject    Asunto del email
 *   @param string $html       Contenido HTML
 *   @param string $text       Contenido texto plano
 *   @param array  $headers    Headers del email
 *   @return array Destinatarios modificados
 * 
 * - 'rfq_before_send_email_subject': Modificar asunto antes del envío
 * - 'rfq_before_send_email_html': Modificar contenido HTML antes del envío
 * - 'rfq_before_send_email_headers': Modificar headers antes del envío
 * 
 * Acciones post-envío:
 * 
 * - 'rfq_after_send_email': Ejecutar acciones después del envío
 *   @param bool   $result     Resultado del wp_mail()
 *   @param array  $recipients Lista de destinatarios
 *   @param string $subject    Asunto enviado
 *   @param string $html       Contenido HTML enviado
 *   @param array  $headers    Headers enviados
 */