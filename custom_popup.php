<?php
/**
 * Plugin Name: Custom Popup
 * Description: Displays a popup via shortcode with a form, and saving to a CSV file and sends email with information about nwe entry. Allows browsing and editing saved data and configuring the popup from the WordPress menu.
 * Version: 1.6
 * Author: Wowk Digital
 */

// Prevent direct access
if ( !defined('ABSPATH') ) {
    exit;
}

// Function to generate the popup
function custom_popup_shortcode() {
    $title = get_option('custom_popup_title', 'Welcome to Yoga Holidays!');
    $content = get_option('custom_popup_content', 'Enter your phone number and receive a discount!');
    $custom_css = get_option('custom_popup_css', '');

    ob_start();
    ?>
    <style>
        #custom-popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        #custom-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            border-radius: 15px;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        #custom-popup form {
            display: flex;
            flex-direction: column;
        }
        #custom-popup form input {
            margin-bottom: 10px;
            padding: 10px;
            font-size: 16px;
        }
        #custom-popup form button {
            padding: 10px;
            font-size: 16px;
        }
        #close-popup-btn {
            margin-top: 20px;
            padding-top: 20px;
            text-align: center;
        }
        #success-message {
            display: none;
            color: green;
            margin-top: 20px;
            text-align: center;
        }
        <?php echo $custom_css; ?>
    </style>
    <div id="custom-popup-overlay"></div>
    <div id="custom-popup">
        <h2><?php echo esc_html($title); ?></h2>
        <p><?php echo esc_html($content); ?></p>
        <form id="custom-popup-form">
            <input type="text" id="name" name="name" placeholder="Name" required>
            <input type="tel" id="phone" name="phone" placeholder="Phone Number" required>
            <button type="button" id="claim-discount" disabled>Claim Free Course</button>
        </form>
        <button id="close-popup-btn" onclick="closePopup()">Close</button>
        <div id="success-message">Thank you, your number has been saved. We will contact you soon.</div>
    </div>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.getElementById('custom-popup').style.display = 'block';
                document.getElementById('custom-popup-overlay').style.display = 'block';
                setTimeout(function() {
                    document.getElementById('custom-popup').style.opacity = '1';
                    document.getElementById('custom-popup-overlay').style.opacity = '1';
                }, 10);
            }, 3000);

            const form = document.getElementById('custom-popup-form');
            const nameInput = document.getElementById('name');
            const phoneInput = document.getElementById('phone');
            const claimButton = document.getElementById('claim-discount');
            const successMessage = document.getElementById('success-message');

            form.addEventListener('input', function() {
                if (nameInput.value && phoneInput.value) {
                    claimButton.disabled = false;
                } else {
                    claimButton.disabled = true;
                }
            });

            claimButton.addEventListener('click', function() {
                if (nameInput.value && phoneInput.value) {
                    saveData(nameInput.value, phoneInput.value);
                    successMessage.style.display = 'block';
                    setTimeout(function() {
                        closePopup();
                    }, 2000);
                }
            });
        });

        function closePopup() {
            document.getElementById('custom-popup').style.opacity = '0';
            document.getElementById('custom-popup-overlay').style.opacity = '0';
            setTimeout(function() {
                document.getElementById('custom-popup').style.display = 'none';
                document.getElementById('custom-popup-overlay').style.display = 'none';
            }, 500);
        }

        function saveData(name, phone) {
            const data = new FormData();
            data.append('action', 'save_popup_data');
            data.append('name', name);
            data.append('phone', phone);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: data
            }).then(response => response.json())
              .then(data => {
                  console.log(data.message);
              });
        }
    </script>
    <?php
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('custom_popup', 'custom_popup_shortcode');

// Function to save data to CSV and send email
function save_popup_data() {
    if ( isset($_POST['name']) && isset($_POST['phone']) ) {
        $name = sanitize_text_field($_POST['name']);
        $phone = sanitize_text_field($_POST['phone']);
        $timestamp = current_time('Y-m-d H:i:s');
        $file = fopen(plugin_dir_path(__FILE__) . 'popup_data.csv', 'a');

        fputcsv($file, [$name, $phone, $timestamp]);
        fclose($file);

        // Send email
        $to = ['k.wowk@crazyfejm.pl', 'm.olechowski@crazyfejm.pl', 'kontakt@piotrkurpiasz.com'];
        $subject = 'New popup submission';
        $message = "Name: $name\nPhone Number: $phone\nDate and Time: $timestamp";
        wp_mail($to, $subject, $message);

        wp_send_json_success(['message' => 'Data saved and email sent.']);
    } else {
        wp_send_json_error(['message' => 'Data save error.']);
    }

    wp_die();
}
add_action('wp_ajax_save_popup_data', 'save_popup_data');
add_action('wp_ajax_nopriv_save_popup_data', 'save_popup_data');

// Add an admin page to the WordPress menu
function custom_popup_menu() {
    add_menu_page(
        'Custom Popup Settings',
        'Custom Popup',
        'manage_options',
        'custom-popup-settings',
        'custom_popup_settings_page',
        'dashicons-admin-generic',
        100
    );
}
add_action('admin_menu', 'custom_popup_menu');

// Function to display the popup settings page
function custom_popup_settings_page() {
    if ( !current_user_can('manage_options') ) {
        return;
    }

    if ( isset($_POST['custom_popup_title']) ) {
        update_option('custom_popup_title', sanitize_text_field($_POST['custom_popup_title']));
        update_option('custom_popup_content', sanitize_text_field($_POST['custom_popup_content']));
        update_option('custom_popup_css', wp_kses_post($_POST['custom_popup_css']));
        echo '<div class="updated"><p>Settings saved</p></div>';
    }

    $title = get_option('custom_popup_title', 'Welcome to Yoga Holidays!');
    $content = get_option('custom_popup_content', 'Enter your phone number and receive a discount!');
    $custom_css = get_option('custom_popup_css', '');
    $csv_file = plugin_dir_path(__FILE__) . 'popup_data.csv';

    ?>
    <div class="wrap">
        <h1>Custom Popup Settings</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Popup Title</th>
                    <td><input type="text" name="custom_popup_title" value="<?php echo esc_attr($title); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Popup Content</th>
                    <td><textarea name="custom_popup_content" class="large-text" rows="3"><?php echo esc_textarea($content); ?></textarea></td>
                </tr>
                <tr valign="top">
                    <th scope="row">CSS Styles</th>
                    <td><textarea name="custom_popup_css" class="large-text" rows="10"><?php echo esc_textarea($custom_css); ?></textarea></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <h2>Records</h2>
        <?php if ( file_exists($csv_file) ) : ?>
            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th class="manage-column column-columnname" scope="col">Name</th>
                        <th class="manage-column column-columnname" scope="col">Phone Number</th>
                        <th class="manage-column column-columnname" scope="col">Date and Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ( ($handle = fopen($csv_file, "r")) !== FALSE ) {
                        while ( ($data = fgetcsv($handle, 1000, ",")) !== FALSE ) {
                            echo '<tr>';
                            echo '<td>' . esc_html($data[0]) . '</td>';
                            echo '<td>' . esc_html($data[1]) . '</td>';
                            echo '<td>' . esc_html($data[2]) . '</td>';
                            echo '</tr>';
                        }
                        fclose($handle);
                    }
                    ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No records found.</p>
        <?php endif; ?>
    </div>
    <?php
}
?>
