<?php namespace OneFile;

use Exception;

/**
 *
 * Simple PHP CAPTCHA Class
 *
 * Based on "simple_php_captcha.php" by Cory LaViska - https://github.com/claviska
 *
 * Adapted by: C. Moller - 19 January 2017
 *
 * Licensed under the MIT license: http://opensource.org/licenses/MIT
 *
 * Attribution:
 *  - Special thanks to Subtle Patterns for the patterns used for default backgrounds: http://subtlepatterns.com/
 *  - Special thanks to dafont.com for providing Times New Yorker: http://www.dafont.com/
 *
 * @update:  C. Moller - 11 September 2018
 *  - Refactor code (Break into smaller units)
 *  - Add support for multiple Captcha's per session.
 *  - Multi browser tab protection.
 *
 */

class Captcha
{
  public $imageSrc;
  public $captchaCode;
  public $config;
  
  protected $assetsDir;
  protected $sessionKey;
  protected $captchaConfigs = [];


  public function __construct($scenario, $config = null)
  {
    // Check for GD library
    if( ! extension_loaded('gd')) {
      throw new Exception('Required GD library is missing');
    }

    // We might have multiple Captcha forms in this session, hence
    // we need a Captcha ID to get/set the correct Captcha configuration.
    // If no ID is specified, we assume there is only ONE Captcha form in this session!
    $id = $this->arrVal($config?:[], 'id');

    switch ($scenario)
    {
      case 'render-mode':
        $myConfig = $this->getMyConfig($id);
        $this->config = $this->extendDefaultConfig($myConfig);
        $this->config = $this->limitValues($this->config);
        break;

      case 'validate-mode':
        $myConfig = $this->getMyConfig($id);
        $this->captchaCode = $this->arrVal($myConfig, 'code');
        $this->forgetMe($id);
        break;

      default: // config-mode:
        $myConfig = $this->getMyConfig($id);
        $noExistingConfig = !$myConfig; 
        if ($noExistingConfig)
        {
          $myConfig = $config?:[];
        }
        $this->config = $this->extendDefaultConfig($myConfig);
        $this->config = $this->limitValues($this->config);
        $srcParams = [];
        if ($id) { $srcParams[] = "i=$id"; } // Captcha id
        $srcParams[] = 't=' . urlencode(microtime()); // Cache buster token
        $this->imageSrc = $this->configGet('captcha_uri') . '?' . implode('&', $srcParams);
        // If this Captcha is already initialized, don't recreate 
        // the Captcha code, use the saved one!
        // This prevents changing the Captcha code when the
        // same form is opened in multiple browser TABS.
        // The Captcha code will be cleared when we perform code validation.
        if ($noExistingConfig)
        {
          $this->captchaCode = $this->generateCaptchaCode($this->config);
          // Note: $myConfig does NOT contain ALL the config values like $this->config.
          // It only contains the custom values for this instance.
          $myConfig['code'] = $this->captchaCode;
        }        
        $this->rememberMe($id, $myConfig);
    }
  }


  public function extendDefaultConfig(array $myConfig)
  {
    $defaultConfig = array(
      'code'              => '',
      'shadow'            => true,
      'color'             => '#666',
      'angle_min'         => 0,
      'angle_max'         => 10,
      'min_length'        => 5,
      'max_length'        => 5,
      'min_font_size'     => 28,
      'max_font_size'     => 28,
      'shadow_offset_y'   => 1,
      'shadow_offset_x'   => -1,
      'shadow_color'      => '#fff',
      'assets_dir'        => null,
      'session_key'       => null, // null value is IMPORTANT!
      'captcha_uri'       => 'captcha.php',
      'characters'        => 'ABCDEFGHJKLMNPRSTUVWXYZabcdefghjkmnprstuvwxyz23456789',
      'fonts'             => array('times_new_yorker.ttf'),
      'backgrounds'       => array(
                            '45-degree-fabric.png',
                            'cloth-alike.png',
                            'grey-sandbag.png',
                            'kinda-jean.png',
                            'polyester-lite.png',
                            'stitched-wool.png',
                            'white-carbon.png',
                            'white-wave.png'
                          )
    );

    return array_merge($defaultConfig, $myConfig);
  }


  public function render()
  {
    $this->captchaCode = $this->configGet('code');
    
    // Pick random background
    $backgrounds = $this->configGet('backgrounds');
    if ( ! $backgrounds) { return; }
    $backgroundImage = $this->getAssetsDir('captcha-backgrounds') .
      $backgrounds[mt_rand(0, count($backgrounds) - 1)];
    list($bg_width, $bg_height, $bg_type, $bg_attr) = getimagesize($backgroundImage);

    // Create new Captcha image object with specified color
    $color = $this->hex2rgb($this->configGet('color'));
    $captcha = imagecreatefrompng($backgroundImage);
    $color = imagecolorallocate($captcha, $color['r'], $color['g'], $color['b']);

    // Determine text angle
    $angle = mt_rand($this->configGet('angle_min'),
      $this->configGet('angle_max')) * (mt_rand(0, 1) == 1 ? -1 : 1);

    // Select font randomly
    $fonts = $this->configGet('fonts');
    if ( ! $fonts) { return; }
    $font = $this->getAssetsDir('captcha-fonts') . $fonts[mt_rand(0, count($fonts) - 1)];
    if( ! file_exists($font))
    {
      $error = 'Font file not found: ' . $font;
      throw new Exception($error);
    }
    $font_size = mt_rand($this->configGet('min_font_size'),
      $this->configGet('max_font_size'));
    $text_box_size = imagettfbbox($font_size, $angle, $font, $this->captchaCode);
    $box_width = abs($text_box_size[6] - $text_box_size[2]);
    $box_height = abs($text_box_size[5] - $text_box_size[1]);

     // Determine text position
    $text_pos_x_min = 0; $text_pos_x_max = ($bg_width) - ($box_width);
    $text_pos_x = mt_rand($text_pos_x_min, $text_pos_x_max);
    $text_pos_y_min = $box_height; $text_pos_y_max = ($bg_height) - ($box_height / 2);
    if ($text_pos_y_min > $text_pos_y_max)
    {
      $temp_text_pos_y = $text_pos_y_min;
      $text_pos_y_min = $text_pos_y_max;
      $text_pos_y_max = $temp_text_pos_y;
    }
    $text_pos_y = mt_rand($text_pos_y_min, $text_pos_y_max);

    // Draw shadow
    if ($this->configGet('shadow'))
    {
      $shadow_rgb = $this->hex2rgb($this->configGet('shadow_color'));
      $shadow_color = imagecolorallocate($captcha, $shadow_rgb['r'],
        $shadow_rgb['g'], $shadow_rgb['b']);
      imagettftext($captcha, $font_size, $angle,
        $text_pos_x + $this->configGet('shadow_offset_x'),
        $text_pos_y + $this->configGet('shadow_offset_y'),
        $shadow_color, $font, $this->captchaCode);
    }

    // Draw text
    imagettftext($captcha, $font_size, $angle, $text_pos_x, $text_pos_y,
      $color, $font, $this->captchaCode);

    // Indicate this is an image response
    header("Content-type: image/png");

    // Output image
    imagepng($captcha);
  }


  /**
   * Utility
   */
  protected function arrVal($array, $key, $default = null)
  {
    return isset($array[$key]) ? $array[$key] : $default;
  }


  /**
   * Utility
   */
  public function configGet($key, $default = null)
  {
    return $this->arrVal($this->config, $key, $default);
  }


  /**
   * Utility
   */
  public function hex2rgb($hex, $as_string = false, $as_string_format = '%s,%s,%s')
  {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3)
    {
      $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
      $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
      $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
    }
    else
    {
      $color_val = hexdec($hex);
      $r = 0xFF & ($color_val >> 0x10);
      $g = 0xFF & ($color_val >> 0x8);
      $b = 0xFF & $color_val;
    }

    return $as_string ? sprintf($as_string_format, $r, $g, $b) : compact('r', 'g', 'b');
  }


  /**
   * Override me!
   */
  public function getSessionKey()
  {
    return '__CAPTCHA__';
  }


  /**
   * Override me!
   */
  public function getMyConfig($id = null)
  {
    $this->captchaConfigs = $this->__session_get($this->getSessionKey(), []);
    // Note: If $id is null: NO multi-config!
    return $id ? $this->arrVal($this->captchaConfigs, $id, []) : $this->captchaConfigs;
  }


  /**
   * Override me!
   */
  public function rememberMe($id = null, $myConfig = [])
  {
    // Are we using multiple Captcha's?
    if ($id)
    { // Yes
      unset($myConfig['id']);
      $this->captchaConfigs[$id] = $myConfig;
    }
    else
    { // No
      $this->captchaConfigs = $myConfig;
    }

    $this->__session_put($this->getSessionKey(), $this->captchaConfigs);
  }


  /**
   * Override me!
   */
  public function forgetMe($id = null)
  {
    // Are we using multiple Captcha's?
    if ($id)
    { // Yes
      // Remove only "My Conifg"
      unset($this->captchaConfigs[$id]);

      // If no more configs left?
      if ( ! $this->captchaConfigs)
      {
        // Remove configs array from session
        $this->__session_unset($this->getSessionKey());
      }
      else
      {
        // Save the remaining configs to session
        $this->__session_put($this->getSessionKey(), $this->captchaConfigs);
      }
    }
    else
    { // No
      // Remove the single config array from session
      $this->__session_unset($this->getSessionKey());
    }
  }


  /**
   * Override me!
   */
  public function limitValues(array $config)
  {
    // Restrict certain values
    if ($config['angle_min']     < 0 ) { $config['angle_min']     = 0;  }
    if ($config['angle_max']     > 10) { $config['angle_max']     = 10; }
    if ($config['min_length']    < 1 ) { $config['min_length']    = 1;  }
    if ($config['min_font_size'] < 10) { $config['min_font_size'] = 10; }

    if ($config['max_font_size'] < $config['min_font_size']) {
      $config['max_font_size'] = $config['min_font_size'];
    }
    if ($config['angle_max'] < $config['angle_min']) {
      $config['angle_max'] = $config['angle_min'];
    }

    return $config;
  }


  /**
   * Override me!
   */
  public function generateCaptchaCode(array $config)
  {
    $captchaCode = '';
    $length = mt_rand($config['min_length'], $config['max_length']);
    while (strlen($captchaCode) < $length)
    {
      $captchaCode .= substr($config['characters'],
        mt_rand() % (strlen($config['characters'])), 1);
    }

    return $captchaCode;
  }


  /**
   * Override me!
   */
  public function getAssetsDir()
  {
    return $this->configGet('assets_dir', __DIR__) . '/';
  }


  /**
   *
   * Override me!
   *
   * Use your own implementation of session store if it's not $_SESSION
   * NOTE: PHP automatically serializes objects in $_SESSION
   *
   */
  protected function __session_get($key, $default)
  {
    return $this->arrVal($_SESSION, $key, $default);
  }


  protected function __session_put($key, $value)
  {
    $_SESSION[$key] = $value;
  }


  protected function __session_unset($key)
  {
    unset($_SESSION[$key]);
  }

}
