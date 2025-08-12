<?php
namespace GiVendor\GiPlugin\Email\Notifications\Custom;

use GiVendor\GiPlugin\Email\Templates\TemplateParser;
use GiVendor\GiPlugin\Email\Templates\TemplateRenderer;
use GiVendor\GiPlugin\Email\EmailManager;
use GiVendor\GiPlugin\Utils\RfqLogger;
use GiVendor\GiPlugin\Notifications\WhatsAppNotifier;
use GiVendor\GiPlugin\Notifications\WhatsAppPhone;

class NotificationManager {
    private static $instance = null;
    private $roles = ['user', 'supplier', 'admin'];
    private $tabs = ['user', 'supplier', 'admin', 'configuracion'];
    private $events = [
        'user' => [
            'solicitud_created' => 'Solicitud Creada',
            'cotizacion_submitted' => 'Cotización Recibida',
            'cotizacion_accepted' => 'Cotización Aceptada',
            'solicitud_cancelada' => 'Solicitud Cancelada'
        ],
        'supplier' => [
            'solicitud_created' => 'Nueva solicitud',
            'cotizacion_submitted' => 'Cotización Enviada',
            'cotizacion_accepted' => 'Cotización Aceptada'
        ],
        'admin' => [
        'solicitud_created' => 'Solicitud created',
        'cotizacion_submitted' => 'Cotización submitted',
        'cotizacion_accepted' => 'Cotización accepted',
        'solicitud_cancelada' => 'Solicitud Cancelada'
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
                
                // Registrar toggle de WhatsApp para esta plantilla
                register_setting(
                    $group,
                    "rfq_whatsapp_enable_{$role}_{$event_key}",
                    [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'default' => 'no'
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
        
        // Registrar configuración del remitente global
        register_setting(
            'rfq_legal_footer_group',
            'rfq_email_from_global',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
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
        
        // Registrar configuraciones de WhatsApp
        register_setting(
            'rfq_legal_footer_group',
            'rfq_whatsapp_enabled',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'no'
            ]
        );
        
        register_setting(
            'rfq_legal_footer_group',
            'rfq_whatsapp_api_key',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ]
        );
        
        register_setting(
            'rfq_legal_footer_group',
            'rfq_whatsapp_sender',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ]
        );
        
        register_setting(
            'rfq_legal_footer_group',
            'rfq_whatsapp_lang',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'es'
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
                
                $from_global = sanitize_email($_POST['rfq_email_from_global']);
                update_option('rfq_email_from_global', $from_global);
                
                $bcc_global = sanitize_text_field($_POST['rfq_email_bcc_global']);
                update_option('rfq_email_bcc_global', $bcc_global);
                
                // Procesar configuración de WhatsApp
                $whatsapp_enabled = isset($_POST['rfq_whatsapp_enabled']) ? 'yes' : 'no';
                update_option('rfq_whatsapp_enabled', $whatsapp_enabled);
                
                $whatsapp_api_key = sanitize_text_field($_POST['rfq_whatsapp_api_key'] ?? '');
                update_option('rfq_whatsapp_api_key', $whatsapp_api_key);
                
                $whatsapp_sender = sanitize_text_field($_POST['rfq_whatsapp_sender'] ?? '');
                update_option('rfq_whatsapp_sender', $whatsapp_sender);
                
                $whatsapp_lang = sanitize_text_field($_POST['rfq_whatsapp_lang'] ?? 'es');
                update_option('rfq_whatsapp_lang', $whatsapp_lang);
                
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
                                            <label for="rfq_email_from_global">
                                                <?php _e('Remitente Global (Email)', 'rfq-manager-woocommerce'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="email" 
                                                   id="rfq_email_from_global" 
                                                   name="rfq_email_from_global" 
                                                   value="<?php echo esc_attr(get_option('rfq_email_from_global', '')); ?>" 
                                                   class="regular-text" 
                                                   placeholder="<?php echo esc_attr(get_option('admin_email')); ?>" />
                                            <p class="description">
                                                <?php _e('Email del remitente para <strong>todas</strong> las notificaciones del sistema RFQ. Si se deja vacío, se usará el email por defecto del sitio.', 'rfq-manager-woocommerce'); ?>
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
                                    
                                    <!-- Sección WhatsApp -->
                                    <tr>
                                        <th scope="row" colspan="2" style="padding: 20px 0 10px 0; border-bottom: 1px solid #ddd;">
                                            <h3 style="margin: 0;"><?php _e('Configuración WhatsApp', 'rfq-manager-woocommerce'); ?></h3>
                                        </th>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <?php _e('Habilitar WhatsApp', 'rfq-manager-woocommerce'); ?>
                                        </th>
                                        <td>
                                            <label for="rfq_whatsapp_enabled">
                                                <input type="checkbox" 
                                                       id="rfq_whatsapp_enabled" 
                                                       name="rfq_whatsapp_enabled" 
                                                       value="1" 
                                                       <?php checked(get_option('rfq_whatsapp_enabled'), 'yes'); ?> />
                                                <?php _e('Activar notificaciones por WhatsApp', 'rfq-manager-woocommerce'); ?>
                                            </label>
                                            <p class="description">
                                                <?php _e('Los usuarios podrán recibir notificaciones por WhatsApp si tienen el opt-in activado.', 'rfq-manager-woocommerce'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="rfq_whatsapp_api_key">
                                                <?php _e('API Key de WhatsApp Business', 'rfq-manager-woocommerce'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="password" 
                                                   id="rfq_whatsapp_api_key" 
                                                   name="rfq_whatsapp_api_key" 
                                                   value="<?php echo esc_attr(get_option('rfq_whatsapp_api_key', '')); ?>" 
                                                   class="regular-text" 
                                                   placeholder="EAAAbcd123..." />
                                            <p class="description">
                                                <?php _e('Token de acceso permanente de tu app de WhatsApp Business API.', 'rfq-manager-woocommerce'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="rfq_whatsapp_sender">
                                                <?php _e('ID del Remitente', 'rfq-manager-woocommerce'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="rfq_whatsapp_sender" 
                                                   name="rfq_whatsapp_sender" 
                                                   value="<?php echo esc_attr(get_option('rfq_whatsapp_sender', '')); ?>" 
                                                   class="regular-text" 
                                                   placeholder="123456789012345" />
                                            <p class="description">
                                                <?php _e('ID numérico del número de teléfono registrado en WhatsApp Business.', 'rfq-manager-woocommerce'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="rfq_whatsapp_lang">
                                                <?php _e('Idioma por defecto', 'rfq-manager-woocommerce'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <select id="rfq_whatsapp_lang" name="rfq_whatsapp_lang">
                                                <option value="es" <?php selected(get_option('rfq_whatsapp_lang', 'es'), 'es'); ?>>Español</option>
                                                <option value="en" <?php selected(get_option('rfq_whatsapp_lang', 'es'), 'en'); ?>>English</option>
                                                <option value="pt" <?php selected(get_option('rfq_whatsapp_lang', 'es'), 'pt'); ?>>Português</option>
                                            </select>
                                            <p class="description">
                                                <?php _e('Idioma usado para plantillas de WhatsApp Business (futuro uso).', 'rfq-manager-woocommerce'); ?>
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
                                        
                                        <!-- Toggle para WhatsApp -->
                                        <p style="border-top: 1px solid #ddd; padding-top: 10px; margin-top: 15px;">
                                            <label for="<?php echo esc_attr("rfq_whatsapp_enable_{$active_tab}_{$event_key}"); ?>">
                                                <input type="checkbox" 
                                                       id="<?php echo esc_attr("rfq_whatsapp_enable_{$active_tab}_{$event_key}"); ?>"
                                                       name="<?php echo esc_attr("rfq_whatsapp_enable_{$active_tab}_{$event_key}"); ?>"
                                                       value="yes"
                                                       <?php checked(get_option("rfq_whatsapp_enable_{$active_tab}_{$event_key}"), 'yes'); ?> />
                                                <strong><?php _e('📱 Habilitar envío por WhatsApp', 'rfq-manager-woocommerce'); ?></strong>
                                            </label>
                                            <br><small style="color: #666;">
                                                <?php _e('Los usuarios con opt-in activado recibirán esta notificación también por WhatsApp.', 'rfq-manager-woocommerce'); ?>
                                            </small>
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
                'cotizacion_accepted' => 'Tu cotización ha sido aceptada',
                'status_changed' => 'Tu solicitud ha cambiado de estado',
                'solicitud_cancelada' => 'Has cancelado tu solicitud'
            ],
            'supplier' => [
                'solicitud_created' => 'Nueva solicitud de cotización disponible',
                'cotizacion_submitted' => 'Cotización enviada correctamente',
                'cotizacion_accepted' => '¡Tu cotización ha sido aceptada!'
            ],
            'admin' => [
                'solicitud_created' => 'Nueva solicitud de cotización recibida',
                'cotizacion_submitted' => 'Nueva cotización enviada',
                'cotizacion_accepted' => 'Cotización aceptada por el cliente',
                'solicitud_cancelada' => 'Un cliente ha cancelado una solicitud'
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
                'status_changed' => 'Hola {user_name},<br><br>Tu solicitud "<strong>{request_title}</strong>" ha pasado a estado <em>Histórica</em>. Esto puede deberse a que la fecha de vigencia ha expirado o a que decidiste cancelarla.<br><br>Si lo deseas, puedes volver a enviarla fácilmente desde tu lista de solicitudes:<br>{request_link}<br><br>Gracias por utilizar nuestros servicios.<br><br>— El equipo de {site_name}',
                'solicitud_cancelada' => 'Hola {user_name},<br><br>Tu solicitud "{request_title}" ha sido cancelada correctamente.<br><br>Puedes consultar el historial o crear una nueva solicitud desde tu panel:<br>{request_link}<br><br>Gracias por confiar en {site_name}.'
            ],
            'supplier' => [
                'solicitud_created' => 'Hola {supplier_name},<br><br>Hay una nueva solicitud de cotización disponible.<br><br>Detalles de la solicitud:<br>ID: {request_id}<br>Título: {request_title}<br>Cliente: {customer_name}<br>Fecha de expiración: {request_expiry}<br><br>Productos solicitados:<br>{productos}<br><br>Puedes enviar tu cotización en: {request_link}',
                'cotizacion_submitted' => 'Hola {supplier_name},<br><br>Has enviado una cotización para la solicitud {request_title}.<br><br>Detalles de la cotización:<br>ID: {quote_id}<br>Cliente: {customer_name}<br>Monto total: {quote_amount}<br><br>Productos cotizados:<br>{productos_cotizados}<br><br>Puedes ver tu cotización en: {quote_link}',
                'cotizacion_accepted' => 'Hola {supplier_name},<br><br>¡Felicidades! Tu cotización para la solicitud {request_title} ha sido aceptada.<br><br>Detalles de la cotización aceptada:<br>ID: {quote_id}<br>Cliente: {customer_name}<br>Monto total: {quote_amount}<br><br>El cliente se pondrá en contacto contigo para coordinar los detalles.'
            ],
            'admin' => [
                'solicitud_created' => 'Hola Administrador,<br><br>Se ha creado una nueva solicitud de cotización.<br><br>Detalles de la solicitud:<br>ID: {request_id}<br>Título: {request_title}<br>Cliente: {user_name}<br>Estado: {request_status}<br>Fecha de expiración: {request_expiry}<br><br>Puedes ver los detalles en: {request_link}',
                'cotizacion_submitted' => 'Hola Administrador,<br><br>Se ha enviado una nueva cotización.<br><br>Detalles de la cotización:<br>ID: {quote_id}<br>Solicitud: {request_title}<br>Proveedor: {supplier_name}<br>Cliente: {user_name}<br>Monto total: {quote_amount}<br><br>Puedes ver los detalles en: {quote_link}',
                'cotizacion_accepted' => 'Hola Administrador,<br><br>Una cotización ha sido aceptada.<br><br>Detalles:<br>ID de cotización: {quote_id}<br>Solicitud: {request_title}<br>Proveedor: {supplier_name}<br>Cliente: {user_name}<br>Monto total: {quote_amount}<br><br>Puedes ver los detalles en: {quote_link}',
                'solicitud_cancelada' => 'Estimado administrador,<br><br>El cliente {user_name} ({user_email}) ha cancelado la solicitud "{request_title}".<br><br>Puedes revisar los detalles en el panel de administración:<br>{request_link}'
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
                '{user_dashboard_link}' => __('Enlace al panel del usuario (/mi-perfil/)', 'rfq-manager-woocommerce'),
                '{supplier_dashboard_link}' => __('Enlace al panel del proveedor (/mi-perfil/)', 'rfq-manager-woocommerce'),
                '{admin_dashboard_link}' => __('Enlace al panel de administración de RFQ', 'rfq-manager-woocommerce'),
            ],
            'request' => [
                '{request_id}' => __('ID de la solicitud (formato TCD-XXXXX)', 'rfq-manager-woocommerce'),
                '{request_uuid}' => __('UUID completo de la solicitud', 'rfq-manager-woocommerce'),
                '{request_title}' => __('Título de la solicitud', 'rfq-manager-woocommerce'),
                '{request_description}' => __('Descripción de la solicitud', 'rfq-manager-woocommerce'),
                '{request_status}' => __('Estado actual de la solicitud', 'rfq-manager-woocommerce'),
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
                '{supplier_name}' => __('Nombre del proveedor (empresa o nombre de usuario)', 'rfq-manager-woocommerce'),
                '{supplier_email}' => __('Email del proveedor', 'rfq-manager-woocommerce'),
            ],
            'user_specific' => [
                '{user_name}' => __('Nombre del usuario/cliente', 'rfq-manager-woocommerce'),
                '{user_email}' => __('Email del usuario/cliente', 'rfq-manager-woocommerce'),
                '{first_name}' => __('Nombre de pila del usuario', 'rfq-manager-woocommerce'),
                '{last_name}' => __('Apellidos del usuario', 'rfq-manager-woocommerce'),
                '{new_request_link}' => __('Enlace para crear nueva solicitud', 'rfq-manager-woocommerce'),
                '{history_link}' => __('Enlace al historial de solicitudes (/lista-solicitudes/)', 'rfq-manager-woocommerce'),
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
                '{productos}' => __('Lista de productos solicitados (formato bullet)', 'rfq-manager-woocommerce'),
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
     * Pipeline completo: prepara + envía email + intenta WhatsApp
     * 
     * @since 0.2.0
     * @param string $event Clave del evento (ej: 'user_solicitud_created')
     * @param array $context Array con datos del contexto
     * @param string|array $to Destinatario(s) de email
     * @return bool Resultado del envío de email
     */
    public static function send_notification(string $event, array $context, $to): bool {
        try {
            // 1. Preparar mensaje
            $message = self::prepare_message($event, $context);
            
            // 2. Enviar email
            $email_result = EmailManager::send($to, $message['subject'], $message['html'], $message['text'], $message['headers']);
            
            // 3. Intentar WhatsApp (solo para eventos de usuario, no bloquea si falla)
            if (str_starts_with($event, 'user_')) {
                self::maybe_send_whatsapp($event, $context, $message);
            }
            
            return $email_result;
            
        } catch (\Throwable $e) {
            RfqLogger::error('[send_notification] Excepción', [
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * Envía notificación por WhatsApp si está habilitado y el usuario tiene opt-in
     * 
     * @param string $event Evento como 'user_solicitud_created'
     * @param array $context Contexto del mensaje
     * @param array $message Mensaje preparado por prepare_message()
     * @return void
     */
    public static function maybe_send_whatsapp(string $event, array $context, array $message): void {
        try {
            // Verificar si WhatsApp está habilitado globalmente
            if (!WhatsAppNotifier::is_enabled()) {
                RfqLogger::debug('[whatsapp] Sistema deshabilitado o sin configurar');
                return;
            }
            
            // Extraer rol del evento
            $role = self::extract_role_from_event($event);
            if (!$role) {
                RfqLogger::warn('[whatsapp] No se pudo extraer rol del evento: ' . $event);
                return;
            }
            
            $clean_event = str_replace($role . '_', '', $event);
            
            // Verificar toggle por plantilla
            $template_option = "rfq_whatsapp_enable_{$role}_{$clean_event}";
            if (get_option($template_option) !== 'yes') {
                RfqLogger::debug('[whatsapp] Template deshabilitado', [
                    'option' => $template_option,
                    'event' => $event
                ]);
                return;
            }
            
            // Permitir override global por filtro
            $enabled_for_event = apply_filters('rfq_whatsapp_enabled_for_event', true, $event, $context);
            if (!$enabled_for_event) {
                RfqLogger::debug('[whatsapp] Deshabilitado por filtro para evento: ' . $event);
                return;
            }
            
            // Obtener destinatarios según el contexto
            $recipients = self::get_whatsapp_recipients($event, $context);
            
            if (empty($recipients)) {
                RfqLogger::debug('[whatsapp] No hay destinatarios válidos para: ' . $event);
                return;
            }
            
            // Preparar texto para WhatsApp
            $whatsapp_text = self::prepare_whatsapp_text($event, $context, $message);
            if (empty($whatsapp_text)) {
                RfqLogger::warn('[whatsapp] No se pudo preparar texto para: ' . $event);
                return;
            }
            
            // Enviar a cada destinatario
            $sent_count = 0;
            foreach ($recipients as $recipient) {
                if (WhatsAppNotifier::send_message($recipient['phone'], $whatsapp_text)) {
                    $sent_count++;
                    RfqLogger::info('[whatsapp] Enviado', [
                        'event' => $event,
                        'user_id' => $recipient['user_id'],
                        'phone' => $recipient['phone']
                    ]);
                }
            }
            
            // Hook post-envío
            do_action('rfq_after_send_whatsapp', $sent_count > 0, $event, $context);
            
        } catch (\Throwable $e) {
            RfqLogger::error('[whatsapp] Excepción en maybe_send_whatsapp', [
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Obtiene destinatarios válidos para WhatsApp según el evento
     * 
     * @param string $event
     * @param array $context
     * @return array Array de ['user_id' => int, 'phone' => string]
     */
    private static function get_whatsapp_recipients(string $event, array $context): array {
        $recipients = [];
        
        // Solo procesamos eventos de usuario por ahora
        if (!str_starts_with($event, 'user_')) {
            return [];
        }
        
        $user_id = $context['user_id'] ?? null;
        if (!$user_id) {
            return [];
        }
        
        // Verificar opt-in del usuario (meta key exacto del plugin gi-user-register)
        $has_optin = get_user_meta($user_id, 'whatsapp_notifications', true);
        if (!$has_optin || $has_optin !== '1') {
            RfqLogger::debug('[whatsapp] Usuario sin opt-in', ['user_id' => $user_id]);
            return [];
        }
        
        // Obtener y validar teléfono
        $phone = WhatsAppPhone::get_user_phone($user_id);
        if (!$phone) {
            RfqLogger::debug('[whatsapp] Usuario sin teléfono válido', ['user_id' => $user_id]);
            return [];
        }
        
        $recipients[] = [
            'user_id' => $user_id,
            'phone' => $phone
        ];
        
        return $recipients;
    }
    
    /**
     * Prepara el texto a enviar por WhatsApp desde el mensaje preparado
     * 
     * @param string $event
     * @param array $context
     * @param array $message
     * @return string
     */
    private static function prepare_whatsapp_text(string $event, array $context, array $message): string {
        // Empezar con el texto plano si existe
        $text = $message['text'] ?? '';
        
        // Si no hay texto plano, derivar del subject + extracto del HTML
        if (empty($text) && !empty($message['html'])) {
            $subject = $message['subject'] ?? '';
            
            // Extraer texto del HTML removiendo tags
            $html_text = wp_strip_all_tags($message['html']);
            
            // Limpiar espacios en blanco excesivos
            $html_text = preg_replace('/\s+/', ' ', trim($html_text));
            
            // Truncar si es muy largo (WhatsApp tiene límites)
            if (strlen($html_text) > 300) {
                $html_text = substr($html_text, 0, 297) . '...';
            }
            
            $text = $subject . "\n\n" . $html_text;
        }
        
        // Filtro para personalizar el texto
        $text = apply_filters('rfq_whatsapp_text', $text, $event, $context, $message);
        
        return trim($text);
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
            // Variables comunes básicas
            'site_name' => get_bloginfo('name'),
            'site_url' => get_bloginfo('url'),
            'admin_email' => get_option('admin_email'),
            'fecha' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
            
            // Enlaces de dashboard según el rol
            'user_dashboard_link' => home_url('/mi-perfil/'),
            'supplier_dashboard_link' => home_url('/mi-perfil/'),
            'admin_dashboard_link' => admin_url('admin.php?page=rfq-manager'),
            'history_link' => home_url('/lista-solicitudes/')
        ];
        
        // Resolver first_name y last_name si hay usuario en contexto
        if (isset($context['user_id'])) {
            $names = self::resolve_user_names($context['user_id']);
            $vars['first_name'] = $names['first_name'];
            $vars['last_name'] = $names['last_name'];
        }
        
        // Resolver datos de solicitud si existe
        if (isset($context['solicitud_id'])) {
            $solicitud_id = $context['solicitud_id'];
            $solicitud = get_post($solicitud_id);
            
            if ($solicitud) {
                // Request ID: TCD-{ÚLTIMOS 5 NÚMEROS DEL UUID}
                $uuid = get_post_meta($solicitud_id, '_solicitud_uuid', true);
                if ($uuid && strlen($uuid) >= 5) {
                    $last_5_chars = substr(preg_replace('/[^0-9]/', '', $uuid), -5);
                    $vars['request_id'] = 'TCD-' . str_pad($last_5_chars, 5, '0', STR_PAD_LEFT);
                } else {
                    // Fallback al ID del post si no hay UUID
                    $vars['request_id'] = 'TCD-' . str_pad($solicitud_id, 5, '0', STR_PAD_LEFT);
                }
                
                // Datos básicos de la solicitud
                $vars['request_uuid'] = $uuid;
                $vars['request_title'] = $solicitud->post_title ?: __('Solicitud sin título', 'rfq-manager-woocommerce');
                $vars['request_status'] = $solicitud->post_status;
                $vars['request_link'] = get_permalink($solicitud_id) ?: admin_url("post.php?post={$solicitud_id}&action=edit");
                
                // Fecha de expiración
                $expiry = get_post_meta($solicitud_id, '_solicitud_expiry', true);
                $vars['request_expiry'] = $expiry ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($expiry)) : __('No definida', 'rfq-manager-woocommerce');
                
                // Productos (items de la solicitud)
                $items = get_post_meta($solicitud_id, '_solicitud_items', true);
                if (is_string($items)) {
                    $items = json_decode($items, true);
                }
                
                $productos_text = '';
                if (is_array($items) && !empty($items)) {
                    foreach ($items as $item) {
                        $productos_text .= "• " . ($item['name'] ?? 'Producto') . " x " . ($item['quantity'] ?? 1) . "\n";
                    }
                } else {
                    $productos_text = __('No se encontraron productos', 'rfq-manager-woocommerce');
                }
                $vars['productos'] = trim($productos_text);
                
                // Contar cotizaciones recibidas
                $quotes_query = new \WP_Query([
                    'post_type' => 'cotizacion',
                    'meta_query' => [
                        [
                            'key' => '_solicitud_parent',
                            'value' => $solicitud_id,
                            'compare' => '='
                        ]
                    ],
                    'post_status' => ['publish', 'draft', 'rfq-accepted'],
                    'fields' => 'ids'
                ]);
                $vars['quotes_count'] = $quotes_query->found_posts;
                wp_reset_postdata();
                
                // Datos del autor de la solicitud (cliente)
                $author_id = $solicitud->post_author;
                if ($author_id) {
                    $author = get_userdata($author_id);
                    $author_names = self::resolve_user_names($author_id);
                    
                    if ($author) {
                        $vars['customer_name'] = $author->display_name;
                        $vars['customer_email'] = $author->user_email;
                        $vars['first_name'] = $author_names['first_name'];
                        $vars['last_name'] = $author_names['last_name'];
                        
                        // Para usuario también es user_name
                        if ($role === 'user') {
                            $vars['user_name'] = $vars['customer_name'];
                            $vars['user_email'] = $vars['customer_email'];
                        }
                    }
                }
            }
        }
        
        // Resolver datos de cotización si existe
        if (isset($context['cotizacion_id'])) {
            $cotizacion_id = $context['cotizacion_id'];
            $cotizacion = get_post($cotizacion_id);
            
            if ($cotizacion) {
                // Datos básicos de la cotización
                $total = get_post_meta($cotizacion_id, '_total', true);
                $vars['total'] = $total ? wc_price($total) : __('No definido', 'rfq-manager-woocommerce');
                
                $created_date = $cotizacion->post_date;
                $vars['quote_date'] = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($created_date));
                
                $vars['quote_link'] = admin_url("post.php?post={$cotizacion_id}&action=edit");
            }
        }
        
        // Resolver datos de proveedor si hay supplier_id en contexto
        if (isset($context['supplier_id'])) {
            $supplier_id = $context['supplier_id'];
            $supplier = get_userdata($supplier_id);
            
            if ($supplier) {
                // Para proveedores, intentar obtener nombre de empresa primero
                $empresa_name = get_user_meta($supplier_id, 'billing_company', true);
                if (empty($empresa_name)) {
                    $empresa_name = get_user_meta($supplier_id, 'company', true);
                }
                
                $vars['supplier_name'] = !empty($empresa_name) ? $empresa_name : $supplier->display_name;
                $vars['supplier_email'] = $supplier->user_email;
            }
        }
        
        // Datos de cambio de estado si existen
        if (isset($context['new_status'])) {
            $vars['status_new'] = $context['new_status'];
        }
        if (isset($context['old_status'])) {
            $vars['status_old'] = $context['old_status'];
        }
        
        // Datos del administrador
        $admin_user = get_userdata(1); // Usuario ID 1 suele ser el admin principal
        if (!$admin_user) {
            $admin_users = get_users(['role' => 'administrator', 'number' => 1]);
            $admin_user = !empty($admin_users) ? $admin_users[0] : null;
        }
        
        if ($admin_user) {
            $vars['admin_name'] = $admin_user->display_name;
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