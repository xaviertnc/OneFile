<?php defined( '__ENTRY_POINT_OK__' ) or die( 'Invalid Entry Point' );

class HtmlSelect
{
	public
		$fieldname,$label,$value,$options,$width,$error,$attributes,$valueformat,$firstoption,
		$term,$empty_message;

	public function __construct($fieldname, $label='', $value='', $term='', &$options=array(), $rows=7, $width='', $error='', &$attributes=array())
	{
		$attributes['name'] = $fieldname;

		File::log('Selects','SelectList::construct(), $fieldname='.$fieldname);

		$value = Html::prep($value);

		if($rows)
			$attributes['size'] = $rows;

		if(!isset($attributes['tabindex']))
			$attributes['tabindex'] = Html::tabindex();

		if(isset($attributes['valueformat']))
		{
			$this->valueformat = $attributes['valueformat'];
			unset($attributes['valueformat']);
		}
		else
			$this->valueformat = 'id';

		if(isset($attributes['firstoption']))
		{
			$this->firstoption = $attributes['firstoption'];
			unset($attributes['firstoption']);
		}
		else
			$this->firstoption = '';

		if(isset($attributes['emptymessage']))
		{
			$this->empty_message = $attributes['emptymessage'];
			unset($attributes['emptymessage']);
		}
		else
			$this->empty_message = 'No Items';

		$this->fieldname = $fieldname;
		$this->label = $label;
		$this->value = $value;
		$this->term = $term;
		$this->width = $width;
		$this->error = $error;
		$this->attributes = &$attributes;
		$this->options = &$options;

	}

	protected function render_first_option($firstoption,$selectedvalue,$valueformat)
	{
		if (!$firstoption) return '';
		$html='';
		$utf8_firstoption = Html::prep($firstoption);
		switch ($valueformat)
		{
			case 'id':
				if ($selectedvalue == 0)
				{
					$html .= '<option value="0" selected>'.$utf8_firstoption.'</option>';
				}
				else
				{
					$html .= '<option value="0">'.$utf8_firstoption.'</option>';
				}
				break;
			case 'combo':
				if ($selectedvalue == 0)
				{
					$html .= '<option value="'.$utf8_firstoption.'|0" selected>'.$utf8_firstoption.'</option>';
				}
				else
				{
					$html .= '<option value="'.$utf8_firstoption.'|0">'.$utf8_firstoption.'</option>';
				}
				break;
			case 'disp':
			default:
				if ($selectedvalue == $firstoption)
				{
					$html .= '<option value="'.$utf8_firstoption.'" selected>'.$utf8_firstoption.'</option>';
				}
				else
				{
					$html .= '<option value="'.$utf8_firstoption.'">'.$utf8_firstoption.'</option>';
				}
		}
		return $html;
	}

	protected function render_options()
	{
		File::log('Selects','SelectList::render_options(), Start');
		
		if(empty($this->options)) return $this->empty_message;

		//NOTE: $list HAS to be an assoc array! with "option_value => option_text" pairs.
		if($this->valueformat == 'combo')
		{
			$parts = explode('|', $this->value);
			
			if(isset($parts[1]))
				$this->value = $parts[1];
		}
		
		$html = $this->render_first_option($this->firstoption,$this->value,$this->valueformat);
		foreach($this->options as $option_value => $option_text)
		{
			$utf8_value = Html::prep($option_text);
			switch ($this->valueformat)
			{

				case 'id':
					//ValueFormat id:  Value = value , Disp = text
					if ($option_value == 0) continue;
					if ($option_value == $this->value)
					{
						$html .= '<option value="'.$option_value.'" selected>'.$utf8_value.'</option>';
					}
					else
					{
						$html .= '<option value="'.$option_value.'">'.$utf8_value.'</option>';
					}
					break;
				case 'combo':
					//ValueFormat combo:  Value = text|value , Disp = text
 					if ($option_value == 0) continue;
					if ($option_value == $this->value)
					{
						$html .= '<option value="'.$utf8_value.'|'.$option_value.'" selected>'.$utf8_value.'</option>';
					}
					else
					{
						$html .= '<option value="'.$utf8_value.'|'.$option_value.'">'.$utf8_value.'</option>';
					}
					break;
				case 'disp':
				default: //ValueFormat disp:  Value = text , Disp = text
					if ($option_text == 'Not Available') continue; //Hack for BAD Database Data & Design ;(
					if ($option_text == $this->value)
					{
						$html .= '<option value="'.$utf8_value.'" selected>'.$utf8_value.'</option>';
					}
					else
					{
						$html .= '<option value="'.$utf8_value.'">'.$utf8_value.'</option>';
					}
			}
		}
		return $html;
	}

	public function render($print=true)
	{
		if(!isset($this->attributes['id']))
			$this->attributes['id'] = $this->fieldname;

		if($this->width)
		{
			if(isset($this->attributes['style']))
				$this->attributes['style'] = trim($this->attributes['style'],';') . ';width:'.$this->width.'px';
			else
				$this->attributes['style'] = 'width:'.$this->width.'px';
		}

		$html = '<select'.Html::renderAttributes($this->attributes).'>'.$this->render_options().'</select>';

		Html::add_field_wrapper($this->attributes['id'],$html,$this->label,$this->error);

		if($print) print($html);
		return $html;
	}
}