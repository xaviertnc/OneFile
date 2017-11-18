<?php namespace OneFile;

use Log; // Remove! For debug only.

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
 */

class Captcha
{
	public $imageSrc;
	public $captchaCode;
	public $captchaInput;

	protected $config;
	protected $captchaKey;
	protected $assetsDir;


	public function __construct($scenario, $options = null)
	{
		// Check for GD library
		if( ! extension_loaded('gd')) throw new Exception('Required GD library is missing');


		switch ($scenario)
		{
			case 'render-mode':
				$this->config = $this->__session_get($this->getCaptchaKey());
				$this->captchaCode = $this->getConfig('code');
				Log::captcha('OneFile.Captcha::construct(render), scenario = ' . $scenario); // . ', self: ' . print_r($this, true));
				break;

			case 'validate-mode':
				$this->config = $this->__session_get($this->getCaptchaKey());
				$this->__session_unset($this->getCaptchaKey());
				$this->captchaCode = $this->getConfig('code');
				$this->captchaInput = $this->getCaptchaInput();
				Log::captcha('OneFile.Captcha::construct(validate), scenario = ' . $scenario);
				break;

			default: // config-mode:
				$this->config = $this->configure($options ?: []);
				$this->imageSrc = $this->getConfig('captcha_uri') . '?t=' . urlencode(microtime()); // cache buster
				$this->captchaCode = $this->getConfig('code');
				$this->__session_put($this->getCaptchaKey(), $this->config);
				Log::captcha('OneFile.Captcha::construct(config), scenario = ' . $scenario);
		}
	}


	public function getConfig($key, $default = null)
	{
	    return isset($this->config[$key]) ? $this->config[$key] : $default;
	}
	

	public function configure(array $options)
	{
		// Default config
		$config = array(
			'code' => '',
			'shadow' => true,
			'color' => '#666',
			'angle_min' => 0,
			'angle_max' => 10,
			'min_length' => 5,
			'max_length' => 5,
			'min_font_size' => 28,
			'max_font_size' => 28,
			'shadow_offset_y' => 1,
			'shadow_offset_x' => -1,
			'shadow_color' => '#fff',
			'assets_dir' => null,
			'captcha_key' => null, // null value is IMPORTANT!
			'captcha_uri' => 'captcha.php',
			'characters' => 'ABCDEFGHJKLMNPRSTUVWXYZabcdefghjkmnprstuvwxyz23456789',
			'fonts' => array('times_new_yorker.ttf'),
			'backgrounds' => array(
				'45-degree-fabric.png',
				'cloth-alike.png',
				'grey-sandbag.png',
				'kinda-jean.png',
				'polyester-lite.png',
				'stitched-wool.png',
				'white-carbon.png',
				'white-wave.png'
			),
		);

		// Overwrite defaults with custom config values
		foreach ($options as $key => $value) { $config[$key] = $value; }

		// Restrict certain values
		if ($config['angle_min'] < 0) $config['angle_min'] = 0;
		if ($config['angle_max'] > 10) $config['angle_max'] = 10;
		if ($config['min_length'] < 1) $config['min_length'] = 1;
		if ($config['min_font_size'] < 10) $config['min_font_size'] = 10;
		if ($config['max_font_size'] < $config['min_font_size']) $config['max_font_size'] = $config['min_font_size'];
		if ($config['angle_max'] < $config['angle_min']) $config['angle_max'] = $config['angle_min'];

		// Generate CAPTCHA code if not set by user
		if (empty($config['code'])) {
			$length = mt_rand($config['min_length'], $config['max_length']);
			while (strlen($config['code']) < $length) {
				$config['code'] .= substr($config['characters'], mt_rand() % (strlen($config['characters'])), 1);
			}
		}

		return $config;
	}


	public function render()
	{
		// Pick random background, get info, and start captcha
		$backgrounds = $this->getConfig('backgrounds'); if ( ! $backgrounds) return;
		$background = $this->getAssetsDir('background') . $backgrounds[mt_rand(0, count($backgrounds) - 1)];
		list($bg_width, $bg_height, $bg_type, $bg_attr) = getimagesize($background);
		$color = $this->hex2rgb($this->getConfig('color'));
		$captcha = imagecreatefrompng($background);
		$color = imagecolorallocate($captcha, $color['r'], $color['g'], $color['b']);
		$angle = mt_rand($this->getConfig('angle_min'), $this->getConfig('angle_max')) * (mt_rand(0, 1) == 1 ? -1 : 1);		// Determine text angle
		$fonts = $this->getConfig('fonts'); if ( ! $fonts) return;
		$font = $this->getAssetsDir('font') . $fonts[mt_rand(0, count($fonts) - 1)];	// Select font randomly
		if( ! file_exists($font) ) { $error = 'Font file not found: ' . $font; throw new Exception($error); }
		$font_size = mt_rand($this->getConfig('min_font_size'), $this->getConfig('max_font_size'));
		$text_box_size = imagettfbbox($font_size, $angle, $font, $this->captchaCode);
		$box_width = abs($text_box_size[6] - $text_box_size[2]);
		$box_height = abs($text_box_size[5] - $text_box_size[1]);
		$text_pos_x_min = 0; $text_pos_x_max = ($bg_width) - ($box_width);												// Determine text position
		$text_pos_x = mt_rand($text_pos_x_min, $text_pos_x_max);
		$text_pos_y_min = $box_height; $text_pos_y_max = ($bg_height) - ($box_height / 2);
		if ($text_pos_y_min > $text_pos_y_max) { $temp_text_pos_y = $text_pos_y_min; $text_pos_y_min = $text_pos_y_max; $text_pos_y_max = $temp_text_pos_y; }
		$text_pos_y = mt_rand($text_pos_y_min, $text_pos_y_max);
		if ($this->getConfig('shadow')) {
			$shadow_color = $this->hex2rgb($this->getConfig('shadow_color'));
			$shadow_color = imagecolorallocate($captcha, $shadow_color['r'], $shadow_color['g'], $shadow_color['b']);
			imagettftext($captcha, $font_size, $angle, $text_pos_x + $this->getConfig('shadow_offset_x'), $text_pos_y +	// Draw shadow
				$this->getConfig('shadow_offset_y'), $shadow_color, $font, $this->captchaCode);
		}
		imagettftext($captcha, $font_size, $angle, $text_pos_x, $text_pos_y, $color, $font, $this->captchaCode);		// Draw text
		header("Content-type: image/png");																				// Indicate this is an image response
		imagepng($captcha);																								// Output image
	}


    public function hex2rgb($hex, $as_string = false, $as_string_format = '%s,%s,%s')
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
        } else {
            $color_val = hexdec($hex);
            $r = 0xFF & ($color_val >> 0x10);
            $g = 0xFF & ($color_val >> 0x8);
            $b = 0xFF & $color_val;
		}
        return $as_string ? sprintf($as_string_format, $r, $g, $b) : compact('r', 'g', 'b');
    }


	public function isValid()
	{
		return $this->captchaCode == $this->captchaInput;
	}


	/**
	 * Override me!
	 */
	public function getCaptchaKey($default = '__CAPTCHA__')
	{
		if ($this->captchaKey) return $this->captchaKey;
		$this->captchaKey = $this->getConfig('captcha_key');
		if ( ! $this->captchaKey) { $this->captchaKey = $default; $this->config['captcha_key'] = $default; }
		return $this->captchaKey;
	}


	/**
	 * Override me!
	 */
	public function getAssetsDir($assetType = null, $default = __DIR__)
	{
		switch ($assetType)
		{
			case 'font':
			case 'background':
			default:
				if ( ! $this->assetsDir) {
					$this->assetsDir = $this->getConfig('assets_dir');
					if ( ! $this->assetsDir) { $this->assetsDir = $default; $this->config['assets_dir'] = $default; }
				}
		}

		return rtrim($this->assetsDir, '/') . '/';
	}


	/**
	 * Override me!
	 */
	public function getCaptchaInput($default = null)
	{
		$captcha_input_name = $this->getCaptchaKey();
		return isset($_REQUEST[$captcha_input_name]) ? $_REQUEST[$captcha_input_name] : $default;
	}


	/**
	 *
	 * Override me!
	 *
	 * Use your own implementation of session store if it's not $_SESSION
	 * NOTE: PHP automatically serializes objects in $_SESSION
	 *
	 */
	protected function __session_put($key, $value) { $_SESSION[$key] = $value; }
	protected function __session_get($key, $default) { return isset($_SESSION[$key]) ? $_SESSION[$key] : $default; }
	protected function __session_unset($key) { unset($_SESSION[$key]); }

}
