<?php
/**
 * Handler centralizado para permisos y redirecciones
 *
 * @package    GiVendor\GiPlugin\Auth
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Auth;

/**
 * PermissionHandler - Maneja permisos y redirecciones de forma centralizada
 *
 * Esta clase centraliza toda la lógica de permisos y redirecciones 
 * para evitar duplicación de código y facilitar el mantenimiento.
 *
 * @package    GiVendor\GiPlugin\Auth
 * @since      0.1.0
 */
class PermissionHandler {
    
    /**
     * Tipos de página disponibles
     */
    const PAGE_TYPE_COTIZAR = 'cotizar';
    const PAGE_TYPE_VER_SOLICITUD = 'ver_solicitud';
    const PAGE_TYPE_LISTA_SOLICITUDES = 'lista_solicitudes';
    
    /**
     * Resultado de verificación de permisos
     */
    const RESULT_ALLOWED = 'allowed';
    const RESULT_REDIRECT = 'redirect';
    const RESULT_ERROR = 'error';
    const RESULT_LOGIN_REQUIRED = 'login_required';
    
    /**
     * Verifica permisos para una página específica
     *
     * @param string $page_type Tipo de página (cotizar, ver_solicitud, lista_solicitudes)
     * @param string $slug Slug de la solicitud (opcional)
     * @return array Array con resultado de la verificación
     */
    public static function check_page_permissions(string $page_type, string $slug = ''): array {
        // Si no está logueado
        if (!is_user_logged_in()) {
            $current_url = self::get_current_url();
            $login_url = home_url('/iniciar-sesion/') . '?redirect_to=' . urlencode($current_url);
            
            return [
                'result' => self::RESULT_LOGIN_REQUIRED,
                'redirect_url' => $login_url,
                'message' => __('Debes iniciar sesión para acceder a esta página.', 'rfq-manager-woocommerce')
            ];
        }
        
        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;
        
        switch ($page_type) {
            case self::PAGE_TYPE_COTIZAR:
                return self::check_cotizar_permissions($user_roles, $slug);
                
            case self::PAGE_TYPE_VER_SOLICITUD:
                return self::check_ver_solicitud_permissions($user_roles, $slug);
                
            case self::PAGE_TYPE_LISTA_SOLICITUDES:
                return self::check_lista_solicitudes_permissions($user_roles);
                
            default:
                return [
                    'result' => self::RESULT_ERROR,
                    'message' => __('Tipo de página no válido.', 'rfq-manager-woocommerce')
                ];
        }
    }

    /**
     * Verifica permisos para página de cotizar
     *
     * @param array $user_roles Roles del usuario
     * @param string $slug Slug de la solicitud
     * @return array
     */
    private static function check_cotizar_permissions(array $user_roles, string $slug): array {

        $solicitud = get_page_by_path($slug, OBJECT, 'solicitud');
        // Si es proveedor, puede acceder SOLO si la solicitud no está en estado historica o aceptada
        if (in_array('proveedor', $user_roles)) {
            if ($solicitud) {
                $estado = get_post_meta($solicitud->ID, 'estado', true); // Ajusta el meta key si es diferente
                // Bloquear si el estado meta o el post_status es rfq-accepted o rfq-historic
                if (in_array($estado, ['rfq-accepted', 'rfq-historic']) || in_array($solicitud->post_status, ['rfq-accepted', 'rfq-historic'])) {
                    error_log('RFQ ERROR: Proveedor intento acceder a solicitud bloqueada ID=' . $solicitud->ID);
                    // Guardar notificación en transient para mostrar después del redirect
                    set_transient('rfq_blocked_notice_' . get_current_user_id(), __('La solicitud ya no está disponible.', 'rfq-manager-woocommerce'), 60);
                    return [
                        'result' => self::RESULT_REDIRECT,
                        'redirect_url' => home_url('/lista-solicitudes/'),
                        'message' => __('La solicitud ya no está disponible.', 'rfq-manager-woocommerce')
                    ];
                }
            }
            return ['result' => self::RESULT_ALLOWED];
        }
    
        // Si es proveedor, puede acceder
        if (in_array('proveedor', $user_roles)) {
            return ['result' => self::RESULT_ALLOWED];
        }
        
        // Si es cliente o admin, redirigir según sea autor o no (admin tiene permisos de cliente)
        if (in_array('customer', $user_roles) || in_array('subscriber', $user_roles) || in_array('administrator', $user_roles)) {
            if (empty($slug)) {
                return [
                    'result' => self::RESULT_REDIRECT,
                    'redirect_url' => home_url('/lista-solicitudes/'),
                    'message' => __('Redirigiendo a lista de solicitudes...', 'rfq-manager-woocommerce')
                ];
            }
            
            $solicitud = get_page_by_path($slug, OBJECT, 'solicitud');
            if (!$solicitud) {
                return [
                    'result' => self::RESULT_REDIRECT,
                    'redirect_url' => home_url('/lista-solicitudes/'),
                    'message' => __('Solicitud no encontrada.', 'rfq-manager-woocommerce')
                ];
            }
            
            $current_user_id = get_current_user_id();
            if ((int)$solicitud->post_author === (int)$current_user_id) {
                // Es autor, redirigir a ver-solicitud
                return [
                    'result' => self::RESULT_REDIRECT,
                    'redirect_url' => home_url('/ver-solicitud/' . $slug . '/'),
                    'message' => __('Redirigiendo a tu solicitud...', 'rfq-manager-woocommerce')
                ];
            } else {
                // No es autor, redirigir a lista
                return [
                    'result' => self::RESULT_REDIRECT,
                    'redirect_url' => home_url('/lista-solicitudes/'),
                    'message' => __('No tienes permisos para ver esta solicitud.', 'rfq-manager-woocommerce')
                ];
            }
        }
        
        // Otros roles no tienen acceso
        return [
            'result' => self::RESULT_ERROR,
            'message' => __('No tienes permisos para acceder a esta página.', 'rfq-manager-woocommerce')
        ];
    }
    
    /**
     * Verifica permisos para página de ver solicitud
     *
     * @param array $user_roles Roles del usuario
     * @param string $slug Slug de la solicitud
     * @return array
     */
    private static function check_ver_solicitud_permissions(array $user_roles, string $slug): array {
        if (empty($slug)) {
            return [
                'result' => self::RESULT_REDIRECT,
                'redirect_url' => home_url('/lista-solicitudes/'),
                'message' => __('Solicitud no especificada.', 'rfq-manager-woocommerce')
            ];
        }
        
        $solicitud = get_page_by_path($slug, OBJECT, 'solicitud');
        if (!$solicitud) {
            return [
                'result' => self::RESULT_REDIRECT,
                'redirect_url' => home_url('/lista-solicitudes/'),
                'message' => __('Solicitud no encontrada.', 'rfq-manager-woocommerce')
            ];
        }
        
        $current_user_id = get_current_user_id();
        
        // Si es proveedor, redirigir a cotizar-solicitud
        if (in_array('proveedor', $user_roles)) {
            return [
                'result' => self::RESULT_REDIRECT,
                'redirect_url' => home_url('/cotizar-solicitud/' . $slug . '/'),
                'message' => __('Redirigiendo a cotizar solicitud...', 'rfq-manager-woocommerce')
            ];
        }
        
        // Si es cliente o admin, solo puede ver sus propias solicitudes (admin tiene permisos de cliente)
        if (in_array('customer', $user_roles) || in_array('subscriber', $user_roles) || in_array('administrator', $user_roles)) {
            if ((int)$solicitud->post_author === (int)$current_user_id) {
                return ['result' => self::RESULT_ALLOWED];
            } else {
                return [
                    'result' => self::RESULT_REDIRECT,
                    'redirect_url' => home_url('/lista-solicitudes/'),
                    'message' => __('No tienes permisos para ver esta solicitud.', 'rfq-manager-woocommerce')
                ];
            }
        }
        
        // Otros roles no tienen acceso
        return [
            'result' => self::RESULT_ERROR,
            'message' => __('No tienes permisos para acceder a esta página.', 'rfq-manager-woocommerce')
        ];
    }
    
    /**
     * Verifica permisos para página de lista de solicitudes
     *
     * @param array $user_roles Roles del usuario
     * @return array
     */
    private static function check_lista_solicitudes_permissions(array $user_roles): array {
        // Administradores, clientes y proveedores pueden acceder
        $allowed_roles = ['administrator', 'customer', 'subscriber', 'proveedor'];
        
        foreach ($allowed_roles as $role) {
            if (in_array($role, $user_roles)) {
                return ['result' => self::RESULT_ALLOWED];
            }
        }
        
        return [
            'result' => self::RESULT_ERROR,
            'message' => __('No tienes permisos para acceder a esta página.', 'rfq-manager-woocommerce')
        ];
    }
    
    /**
     * Ejecuta la acción según el resultado de verificación de permisos
     *
     * @param array $permission_check Resultado de check_page_permissions
     * @param bool $return_message Si debe retornar mensaje en lugar de redirigir
     * @return string|null Mensaje de error si $return_message es true
     */
    public static function handle_permission_result(array $permission_check, bool $return_message = false): ?string {
        switch ($permission_check['result']) {
            case self::RESULT_ALLOWED:
                return null; // Todo bien, continuar
                
            case self::RESULT_LOGIN_REQUIRED:
                if ($return_message) {
                    return '<div class="rfq-error">' . esc_html($permission_check['message']) . '</div>';
                }
                wp_redirect($permission_check['redirect_url']);
                exit;
                
            case self::RESULT_REDIRECT:
                if ($return_message) {
                    return '<div class="rfq-error">' . esc_html($permission_check['message']) . '</div>';
                }
                wp_redirect($permission_check['redirect_url']);
                exit;
                
            case self::RESULT_ERROR:
                return '<div class="rfq-error">' . esc_html($permission_check['message']) . '</div>';
                
            default:
                return '<div class="rfq-error">' . esc_html__('Error de permisos desconocido.', 'rfq-manager-woocommerce') . '</div>';
        }
    }
    
    /**
     * Método de conveniencia para verificar y manejar permisos en un solo paso
     *
     * @param string $page_type Tipo de página
     * @param string $slug Slug de la solicitud (opcional)
     * @param bool $return_message Si debe retornar mensaje en lugar de redirigir
     * @return string|null
     */
    public static function check_and_handle_permissions(string $page_type, string $slug = '', bool $return_message = false): ?string {
        $permission_check = self::check_page_permissions($page_type, $slug);
        return self::handle_permission_result($permission_check, $return_message);
    }
    
    /**
     * Obtiene la URL actual
     *
     * @return string
     */
    private static function get_current_url(): string {
        return home_url(add_query_arg(array(), $_SERVER['REQUEST_URI']));
    }
    
    /**
     * Hook para template_redirect - maneja redirecciones automáticas
     */
    public static function template_redirect_handler(): void {
        global $wp;
        $request_path = $wp->request;
        
        // Verificar páginas protegidas
        if (preg_match('#^cotizar-solicitud/([^/]+)/?$#', $request_path, $matches)) {
            $slug = $matches[1];
            $permission_check = self::check_page_permissions(self::PAGE_TYPE_COTIZAR, $slug);
            self::handle_permission_result($permission_check, false);
        }
        
        if (preg_match('#^ver-solicitud/([^/]+)/?$#', $request_path, $matches)) {
            $slug = $matches[1];
            $permission_check = self::check_page_permissions(self::PAGE_TYPE_VER_SOLICITUD, $slug);
            self::handle_permission_result($permission_check, false);
        }
        
        if (preg_match('#^lista-solicitudes/?$#', $request_path)) {
            $permission_check = self::check_page_permissions(self::PAGE_TYPE_LISTA_SOLICITUDES);
            self::handle_permission_result($permission_check, false);
        }
    }
    
    /**
     * Inicializa el handler de permisos
     */
    public static function init(): void {
        add_action('template_redirect', [self::class, 'template_redirect_handler'], 5);
        add_filter('login_redirect', [self::class, 'handle_login_redirect_filter'], 10, 3);
        add_action('wp_head', [self::class, 'add_redirect_script_to_login_page']);
        // Solo mostrar el toast en frontend (no admin_notices)
        add_action('wp_footer', [self::class, 'show_blocked_notice']);
    }
    
    /**
     * Filtro para manejar la redirección después del login
     * Este filtro tiene prioridad sobre la redirección por defecto de WordPress
     *
     * @param string $redirect_to URL a la que redirigir
     * @param string $request_redirect_to URL solicitada originalmente
     * @param WP_User|WP_Error $user Usuario que ha hecho login
     * @return string URL final de redirección
     */
    public static function handle_login_redirect_filter($redirect_to, $request_redirect_to, $user) {
        // Si hay un error de login, no procesar
        if (is_wp_error($user)) {
            return $redirect_to;
        }
        
        // Si hay un redirect_to solicitado, validarlo y usarlo
        if (!empty($request_redirect_to)) {
            $validated_redirect = wp_validate_redirect($request_redirect_to, home_url());
            if ($validated_redirect) {
                return $validated_redirect;
            }
        }
        
        // También revisar en $_REQUEST por si acaso
        if (isset($_REQUEST['redirect_to']) && !empty($_REQUEST['redirect_to'])) {
            $redirect_from_request = esc_url_raw($_REQUEST['redirect_to']);
            $validated_redirect = wp_validate_redirect($redirect_from_request, home_url());
            if ($validated_redirect) {
                return $validated_redirect;
            }
        }
        
        // Si no hay redirección personalizada, usar la por defecto
        return $redirect_to;
    }
    
    /**
     * Añade JavaScript para manejar redirect_to en páginas de login con Elementor
     */
    public static function add_redirect_script_to_login_page(): void {
        // Solo en la página de iniciar-sesion
        if (is_page('iniciar-sesion')) {
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Obtener el parámetro redirect_to de la URL
                const urlParams = new URLSearchParams(window.location.search);
                const redirectTo = urlParams.get('redirect_to');
                
                console.log('RFQ: Checking for redirect_to parameter:', redirectTo);
                
                if (redirectTo) {
                    // Función para añadir el campo a un formulario
                    function addRedirectField(form) {
                        // Verificar si ya existe el campo para evitar duplicados
                        if (!form.querySelector('input[name="redirect_to"]')) {
                            // Crear campo hidden para redirect_to
                            const hiddenField = document.createElement('input');
                            hiddenField.type = 'hidden';
                            hiddenField.name = 'redirect_to';
                            hiddenField.value = redirectTo;
                            form.appendChild(hiddenField);
                            
                            console.log('RFQ: Campo redirect_to añadido al formulario:', form);
                            console.log('RFQ: Valor del redirect_to:', redirectTo);
                        }
                    }
                    
                    // Buscar formularios de login existentes
                    const forms = document.querySelectorAll('form');
                    let formFound = false;
                    
                    forms.forEach(function(form) {
                        // Si el formulario parece ser de login (tiene campos de usuario/password)
                        if ((form.querySelector('input[type="text"], input[type="email"]') || 
                             form.querySelector('input[name="log"], input[name="user_login"]')) && 
                            form.querySelector('input[type="password"]')) {
                            
                            addRedirectField(form);
                            formFound = true;
                        }
                    });
                    
                    // Si no se encontraron formularios al cargar, usar un observer para detectar cuando se carguen
                    if (!formFound) {
                        console.log('RFQ: No se encontraron formularios inicialmente, configurando observer...');
                        
                        const observer = new MutationObserver(function(mutations) {
                            mutations.forEach(function(mutation) {
                                mutation.addedNodes.forEach(function(node) {
                                    if (node.nodeType === 1) { // Element node
                                        // Buscar formularios en el nodo añadido
                                        let formsInNode = [];
                                        if (node.tagName === 'FORM') {
                                            formsInNode = [node];
                                        } else {
                                            formsInNode = node.querySelectorAll ? node.querySelectorAll('form') : [];
                                        }
                                        
                                        formsInNode.forEach(function(form) {
                                            if ((form.querySelector('input[type="text"], input[type="email"]') || 
                                                 form.querySelector('input[name="log"], input[name="user_login"]')) && 
                                                form.querySelector('input[type="password"]')) {
                                                
                                                addRedirectField(form);
                                                console.log('RFQ: Formulario detectado dinámicamente y procesado');
                                            }
                                        });
                                    }
                                });
                            });
                        });
                        
                        observer.observe(document.body, {
                            childList: true,
                            subtree: true
                        });
                        
                        // Desconectar el observer después de 10 segundos para evitar memory leaks
                        setTimeout(function() {
                            observer.disconnect();
                        }, 10000);
                    }
                }
            });
            </script>
            <?php
        }
    }
        /**
     * Muestra la notificación tipo toast si existe el transient correspondiente
     */
    public static function show_blocked_notice() {
        if (!is_user_logged_in()) return;
        $user_id = get_current_user_id();
        $notice = get_transient('rfq_blocked_notice_' . $user_id);
        if ($notice) {
            // Eliminar el transient para que no se repita
            delete_transient('rfq_blocked_notice_' . $user_id);
            // Mostrar el toast en frontend
            echo '<div id="rfq-toast-blocked" style="display:none;position:fixed;top:30px;right:30px;z-index:9999;background:#e74c3c;color:#fff;padding:16px 24px;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.15);font-size:16px;">' . esc_html($notice) . '</div>';
            echo '<script>document.addEventListener("DOMContentLoaded",function(){var t=document.getElementById("rfq-toast-blocked");if(t){t.style.display="block";setTimeout(function(){t.style.display="none";},4000);}});</script>';
        }
    }
}
