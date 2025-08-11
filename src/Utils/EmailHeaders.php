<?php
/**
 * Email Headers Utility for RFQ System
 * 
 * Centralizes email header generation for all RFQ notification emails
 * to ensure consistency and easier maintenance.
 *
 * @package    GiVendor\GiPlugin\Utils
 * @since      0.1.0
 */

namespace GiVendor\GiPlugin\Utils;

/**
 * EmailHeaders - Centralized email header management
 *
 * This class provides a unified way to generate email headers across
 * all RFQ notification classes, ensuring consistency and reducing 
 * code duplication.
 *
 * @package    GiVendor\GiPlugin\Utils
 * @since      0.1.0
 */
class EmailHeaders {
    
    /**
     * Default from name for RFQ emails
     *
     * @since  0.1.0
     * @access private
     * @var    string
     */
    private static $default_from_name = null;
    
    /**
     * Default from email for RFQ emails
     *
     * @since  0.1.0
     * @access private
     * @var    string
     */
    private static $default_from_email = null;
    
    /**
     * Get standard email headers for RFQ notifications
     *
     * @since  0.1.0
     * @param  string|null $from_name Optional custom from name
     * @param  string|null $from_email Optional custom from email
     * @param  array $additional_headers Optional additional headers
     * @return string Email headers string
     */
    public static function get(
        ?string $from_name = null, 
        ?string $from_email = null, 
        array $additional_headers = []
    ): string {
        // Get default values if not provided
        $from_name = $from_name ?? self::getDefaultFromName();
        $from_email = $from_email ?? self::getDefaultFromEmail();
        
        // Start with content type
        $headers = "Content-Type: text/html; charset=UTF-8\r\n";
        
        // Add from header
        $headers .= "From: " . esc_html($from_name) . " <" . sanitize_email($from_email) . ">\r\n";
        
        // Add any additional headers
        if (!empty($additional_headers)) {
            foreach ($additional_headers as $header) {
                $headers .= $header . "\r\n";
            }
        }
        
        // Apply filter to allow customization
        return apply_filters('rfq_email_headers', $headers);
    }
    
    /**
     * Get headers with BCC for supplier notifications
     *
     * @since  0.1.0
     * @param  array $bcc_emails Array of BCC email addresses
     * @param  string|null $from_name Optional custom from name
     * @param  string|null $from_email Optional custom from email
     * @return string Email headers string with sanitized BCC
     */
    public static function getWithBcc(
        array $bcc_emails, 
        ?string $from_name = null, 
        ?string $from_email = null
    ): string {
        // Sanitize and validate BCC emails
        $sanitized_bcc = self::sanitizeBccEmails($bcc_emails);
        
        $additional_headers = [];
        if (!empty($sanitized_bcc)) {
            $additional_headers[] = 'Bcc: ' . implode(',', $sanitized_bcc);
        }
        
        return self::get($from_name, $from_email, $additional_headers);
    }
    
    /**
     * Sanitize and validate BCC email addresses
     *
     * @since  0.1.0
     * @param  array $emails Array of email addresses
     * @return array Sanitized and valid email addresses
     */
    public static function sanitizeBccEmails(array $emails): array {
        $sanitized = [];
        
        foreach ($emails as $email) {
            // Sanitize the email
            $clean_email = sanitize_email(trim($email));
            
            // Validate the email and ensure it's not empty
            if (!empty($clean_email) && is_email($clean_email)) {
                $sanitized[] = $clean_email;
            } else {
                // Log invalid email for debugging
                RfqLogger::warn("Invalid BCC email address skipped", [
                    'original_email' => $email,
                    'sanitized_email' => $clean_email
                ]);
            }
        }
        
        // Remove duplicates
        return array_unique($sanitized);
    }
    
    /**
     * Get headers for admin notifications with proper recipient validation
     *
     * @since  0.1.0
     * @param  array $admin_recipients Array of admin email addresses  
     * @param  string|null $from_name Optional custom from name
     * @param  string|null $from_email Optional custom from email
     * @return string Email headers string
     */
    public static function getForAdmins(
        array $admin_recipients, 
        ?string $from_name = null, 
        ?string $from_email = null
    ): string {
        // Validate admin recipients
        $valid_recipients = self::validateRecipients($admin_recipients, 'admin');
        
        return self::get($from_name, $from_email);
    }
    
    /**
     * Validate email recipients and log issues
     *
     * @since  0.1.0
     * @param  array $recipients Array of email addresses
     * @param  string $recipient_type Type of recipients (admin, supplier, user)
     * @return array Valid email addresses
     */
    public static function validateRecipients(array $recipients, string $recipient_type = 'unknown'): array {
        $valid = [];
        
        foreach ($recipients as $recipient) {
            $clean_email = sanitize_email(trim($recipient));
            
            if (!empty($clean_email) && is_email($clean_email)) {
                $valid[] = $clean_email;
            } else {
                RfqLogger::warn("Invalid {$recipient_type} recipient email skipped", [
                    'recipient_type' => $recipient_type,
                    'original_email' => $recipient,
                    'sanitized_email' => $clean_email
                ]);
            }
        }
        
        return array_unique($valid);
    }
    
    /**
     * Get default from name for RFQ emails
     *
     * @since  0.1.0
     * @return string Default from name
     */
    private static function getDefaultFromName(): string {
        if (self::$default_from_name === null) {
            self::$default_from_name = get_option('rfq_manager_from_name', get_bloginfo('name'));
        }
        
        return self::$default_from_name;
    }
    
    /**
     * Get default from email for RFQ emails
     *
     * @since  0.1.0
     * @return string Default from email
     */
    private static function getDefaultFromEmail(): string {
        if (self::$default_from_email === null) {
            self::$default_from_email = get_option('rfq_manager_from_email', get_option('admin_email'));
        }
        
        return self::$default_from_email;
    }
    
    /**
     * Set custom default from name (for testing or configuration)
     *
     * @since  0.1.0
     * @param  string $name From name
     * @return void
     */
    public static function setDefaultFromName(string $name): void {
        self::$default_from_name = $name;
    }
    
    /**
     * Set custom default from email (for testing or configuration)
     *
     * @since  0.1.0
     * @param  string $email From email
     * @return void
     */
    public static function setDefaultFromEmail(string $email): void {
        self::$default_from_email = sanitize_email($email);
    }
    
    /**
     * Reset cached defaults (useful for testing)
     *
     * @since  0.1.0
     * @return void
     */
    public static function resetDefaults(): void {
        self::$default_from_name = null;
        self::$default_from_email = null;
    }
}
