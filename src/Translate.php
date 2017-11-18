<?php namespace OneFile;

use Log;

/**
 * OneFile Translation Service
 *
 * @author: C. Moller <xavier.tnc@gmail.com>
 * @date: 22 September 2017
 *
 */

class Translate
{

	protected $lang;
	protected $messages;
	protected $langBaseDirectory;
	protected $langFilePath;


	/**
	 * Loads or initializes history
	 *
	 * @param integer $levels
	 * @param string $history_session_key
	 */
    public function __construct($lang = 'en', $langBaseDirectory = null)
    {
		$this->langBaseDirectory = $langBaseDirectory ?: __DIR__ .  DIRECTORY_SEPARATOR . 'lang';
		//Log::debug('OneFile\Translate::__construct(), lang = ' . print_r($lang, true) . ', langBaseDirectory = ' . print_r($this->langBaseDirectory, true));
		$this->setLanguage($lang);
		//Log::debug('OneFile\Translate::__construct(), messages = ' . print_r($this->messages, true));
    }


	protected function getLangFilePath($lang = 'default')
	{
		//Log::debug('OneFile\Translate::getLangFilePath(), lang = ' . print_r($lang, true));
		return $this->langBaseDirectory . DIRECTORY_SEPARATOR . $lang . '.php';
	}


	protected function setLanguage($lang = null)
	{
		//Log::debug('OneFile\Translate::setLanguage(), lang = ' . print_r($lang, true));

		if ($lang and $lang == $this->lang)
		{
			return;
		}

        $this->lang = $lang;

        $this->langFilePath = $this->getLangFilePath($lang);

		//Log::debug('OneFile\Translate::setLanguage(), langFilePath = ' . print_r($this->langFilePath, true));

        if (!file_exists($this->langFilePath))
        {
			throw new \Exception('Language file: "' . $this->langFilePath . '" could not be found.');
		}

        $this->getMessages();
	}


    /**
     * Get messages.
     *
     * @return array
     */
    protected function getMessages()
    {
		//Log::debug('OneFile\Translate::getMessages()');

		try
		{
			$this->messages = require $this->langFilePath;
		}

		catch (Exception $e)
		{
			throw $e;
		}

        return $this->messages;
    }


    public function getMessage($messageKey, $options = null)
    {
		$params = [];
		$asPlural = false;

		// No key match: Just return the key.
		if (empty($this->messages[$messageKey]))
		{
			return $messageKey;
		}

		// Did we specify options?
		if (isset($options))
		{
			// Yes
			// If the options are in an array, lets unpack into our local vars...
			if (is_array($options))
			{
				$asPlural = isset($options['plural']);

				if (isset($options['lang']))
				{
					$this->setLanguage($options['lang']);
				}

				if (isset($options['params']))
				{
					$params = $options['params'];
				}
			}
			// If $options is set but its NOT an array, its value is taken to be the Plural(Yes/No)? setting.
			else
			{
				$asPlural = !!$options;
			}
		}

		$messageSet = $this->messages[$messageKey];

		// If a message is an array (i.e. MessageSet), it means we have more than one version of this message.
		// Most often this would be the single and plural versions of the message.
		// We might add more message version variants later, but for now... sinlge/plural!
		if (is_array($messageSet))
		{
			$message = ($asPlural and isset($messageSet[1])) ? $messageSet[1] : $messageSet[0];
		}
		else
		{
			$message = $messageSet;
		}

		// If we provided a params value in "options", we assume that our message is a "sprintf" template string
		// and that our $params value/array will be the template's data model.
		if ($params)
		{
			$message = is_array($params) ? call_user_func_array('sprintf', array_merge(array($message), $params)) : sprintf($message, $params);
		}

		return $message;
	}

}
