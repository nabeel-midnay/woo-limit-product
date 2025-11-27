<?php
/********************************************************
Create a unique array that contains all theme settings
********************************************************/
global $wpdb;
class ControlPanel{
    var $default_settings = Array();
    var $options;
    
    //function ControlPanel() {
     function __construct(){
        add_action('admin_menu', array(&$this, 'add_menu'));
        add_action('admin_head', array(&$this, 'admin_head'));
        if (!is_array(get_option('ijwlp_advanced_settings')))
        add_option('ijwlp_advanced_settings', $this->default_settings);
        $this->options = get_option('ijwlp_advanced_settings');   
    
        if(isset($_POST['cp_save'])){
            if ($_POST['cp_save'] == 'Save Changes') {    
                $this->options['enablelimit']      =   isset($_POST['enablelimit']) ? 1 : 0;
                $this->options["limitlabel"]       =  stripslashes($_POST['limitlabel']);
                $this->options["limitlabelcart"]   =  stripslashes($_POST['limitlabelcart']);
                
                // WEBCASTLE FIX - Validate timer limit to ensure it's a valid number
                $limittime = stripslashes($_POST['limittime']);
                if (is_numeric($limittime) && $limittime > 0 && $limittime <= 1440) {
                    $this->options["limittime"] = intval($limittime);
                } else {
                    // If invalid value, set to default 15 minutes
                    $this->options["limittime"] = 15;
                    echo '<div class="error fade" id="message" style="background-color: rgb(255, 204, 204); width: 500px; margin-left: 50px"><p class="pblc">Timer Limit must be a number between 1 and 1440 minutes. Default value of 15 minutes has been set.</p></div>';
                }
                
                update_option('ijwlp_advanced_settings', $this->options);
                echo '<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204); width: 500px; margin-left: 50px"><p class="pblc">Settings <strong>saved</strong>.</p></div>';
            }
        }
        ?>
        <style>
            .settingsTable{
                background: #fff;
                padding: 40px 20px;
                width: 95%;
                border-radius: 0 20px 20px;
            }
            .settingsTable input[type="text"],
            .settingsTable input[type="number"]{
                width: 80%;
                max-width: 500px;
                padding: 0 7px;
                box-sizing: border-box;
                outline: none;
                height: 42px;
                margin: 0;
                border-radius: 6px;
                -webkit-border-radius: 6px;
                -moz-border-radius: 6px;
                background: #FFFFFF;
                border: 1px solid rgba(17,18,20,0.12);
                box-sizing: border-box;
                box-shadow: 0px 2px 6px rgba(60,185,124,0.13);
                font-size: 13px;
                color: #002257;
            }
            .settingsTable td{padding-bottom: 20px}
            .cButton{
                display: inline-block;
                height: 40px;
                background: #2271b1;
                border-radius: 6px;
                border: none;
                outline: none;
                cursor: pointer;
                font-weight: 500;
                font-size: 14px;
                line-height: 16px;
                color: #FFFFFF;
                letter-spacing: 0.1px;
                text-align: center;
                color: #ffffff;
                margin-left: 5px !important;
                width: 100%;
                max-width: 135px;
                box-sizing: border-box;
                position: relative;
                margin-top: 20px;
            }
            .titleText{
                float: left;
                height: 59px;
                line-height: 59px;
                padding: 0 30px 0 30px;
                margin: 0;
                cursor: pointer;
                color: #2271b1;
                border: none;
                outline: none;
                background: #FFFFFF;
                border-bottom: 1px solid rgba(0,0,0,0.08);
                border-radius: 20px 20px 0 0;
                box-sizing: border-box;
                font-size: 14px;
                font-weight: 500;
            }
        </style>
        <form action="" method="post" id="themeform" enctype="multipart/form-data">
            <div class="optionsForm">
                <div class="optionsContents">
                    <div class="titleText">Global Settings</div> 
                    <table cellspacing="5" cellpadding="5" class="settingsTable">
                        <tr>
                            <td width="250">Enable Timer (Disable / Enable Timer)</td>
                            <td><input type="checkbox" name="enablelimit" <?php echo $this->options['enablelimit'] == 1 ? ' checked' : '' ;?> /></td>   
                        </tr>

                        <tr>
                            <td> Listing Page Text</td>
                            <td><input type="text" name="limitlabel" value="<?php echo $this->options["limitlabel"]; ?>" /></td>
                        </tr>
                        <tr>
                            <td> Cart Page Text</td>
                            <td><input type="text" name="limitlabelcart" value="<?php echo $this->options["limitlabelcart"]; ?>" /></td>
                        </tr>
                        <tr>
                            <td> Timer Limit (Default 15 mins)</td>
                            <td><input type="number" name="limittime" min="1" max="60" step="1" value="<?php echo $this->options["limittime"]; ?>" placeholder="Enter time in minutes" /></td>
                        </tr>
                    </table>
                    <div class="cp_separator"></div>                    

                    <table>
                        <tr>
                            <td align="center"><input type="submit" value="Save Changes"   id="buttonID"  name="cp_save" class="cButton" /></td>
                        </tr>
                    </table>
                    <div class="cp_separator"></div>
                
                </div>
        </div>
        </form> 
        
        <script>
        // WEBCASTLE FIX - Client-side validation for Timer Limit field
        document.addEventListener('DOMContentLoaded', function() {
            var timerInput = document.querySelector('input[name="limittime"]');
            if (timerInput) {
                timerInput.addEventListener('input', function() {
                    var value = this.value;
                    var numericValue = parseFloat(value);
                    
                    // Remove non-numeric characters except decimal point
                    if (value && !/^\d*\.?\d*$/.test(value)) {
                        this.value = value.replace(/[^\d.]/g, '');
                    }
                    
                    // Check if value is within valid range
                    if (numericValue < 1) {
                        this.setCustomValidity('Timer must be at least 1 minute');
                    } else if (numericValue > 60) {
                        this.setCustomValidity('Timer cannot exceed 60 minutes (1 hours)');
                    } else {
                        this.setCustomValidity('');
                    }
                });
                
                // Also validate on form submission
                document.getElementById('themeform').addEventListener('submit', function(e) {
                    var value = timerInput.value;
                    var numericValue = parseFloat(value);
                    
                    if (!value || isNaN(numericValue) || numericValue < 1 || numericValue > 60) {
                        e.preventDefault();
                        alert('Timer Limit must be a number between 1 and 60 minutes.');
                        timerInput.focus();
                        return false;
                    }
                });
            }
        });
        </script>
        
        <?php
        } 
}
$cpanel = new ControlPanel();
$theme_options = get_option('ijwlp_advanced_settings');