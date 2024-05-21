<?php namespace F1;

use FPDF\FPDF;

/**
 * F1 PDF Class - 15 Mar 2011
 * 
 * @author  C. Moller <xavier.tnc@gmail.com>
 * 
 * @version 2.0 - UPD - 1 May 2013
 *   - Refactor for Laravel 4
 * 
 * @version 3.0 - UPD - 20 Mar 2024
 *   - Adapt for use with F1 Framework, FPDF 1.86 and PHP 8+
 *   - Change all `var` declarations to `protected`.
 *   - Change use FPDF to use FPDF\FPDF.
 *   - Add PDF::getMargin() and PDF::setMargin().
 *   - Improve PDF::TextBoxSL to allow rendering URLS and cell padding.
 *   - Revert _parsepng and _parsejpg overrides.
 *   - Remove custom Georgia font. Was used for KD app.
 *   - Remove PDF::PutLink().
 * 
 * @version 3.1 - FIX - 21 May 2024
 *   - Fix TextBoxML issue with $lines == null.
 *   - Replace utf8_decode() with mb_convert_encoding().
 *   - Restore removed PDF::PutLink() method since it is still in use!
 */

class PDF extends FPDF
{
  protected $FONT;
  protected $SIZE;
  protected $COLOUR;
  protected $DECOR;
  protected $PREV_FONT;
  protected $PREV_SIZE;
  protected $PREV_COLOUR;
  protected $PREV_DECOR;
  protected $LINE_HEIGHT;
  protected $LINE_SPACING = 3; // In Pt's
  protected $HREF;
  protected $wc; // width of center part of page (i.e. PageWidth - Margins)
  protected $FillColorHex;
  protected $DrawColorHex;
  protected $ShowHeader;
  protected $ShowFooter;


  private function _getLineHeight($fspt)
  {
    return ($fspt + $this->LINE_SPACING)/$this->k;
  }
    
  function __construct($orientation='', $unit='', $pagesize='', $font='', $fontsize='', $fontcolour='', $margins='')
  {
    // Call parent constructor
    if (!$orientation) $orientation='P';
    if (!$unit) $unit='mm';
    if (!$pagesize) $pagesize='A4';
    if (!$font) $font="helvetica";
    if (!$fontsize) $fontsize=13; //In "px"!   16px = 12pt
    if (!$fontcolour) $fontcolour=0;
    $this->Init($orientation,$unit,$pagesize);
    $this->HREF = '';
    $this->FONT = ($font)?$font:$this->FontFamily;
    $this->SIZE = ($fontsize)?$fontsize:$this->FontSizePt;
    $this->COLOUR = ($fontcolour)?$fontcolour:$this->TextColor;
    $this->DECOR = '';
    $this->PREV_FONT = $this->FONT;
    $this->PREV_SIZE = $this->SIZE;
    $this->PREV_COLOUR = $this->COLOUR;
    $this->PREV_DECOR = $this->DECOR;
    $this->LINE_HEIGHT = $this->_getLineHeight($this->SIZE);
    $this->SetFont($this->FONT,$this->DECOR,$this->SIZE);
    $this->SetTextColorHex($this->COLOUR);
    $this->SetMargins($margins,null,$margins);
    $this->SetDrawColor(0,0,255);
    $this->SetFillColor(200,200,200);
    $this->SetX($this->lMargin);
    // $this->AddFont('georgia','IB','georgiaib.php');
    $this->ShowHeader = true;
    $this->ShowFooter = true;
  }

  function Init($orientation='P', $unit='mm', $size='A4')
  {
    parent::__construct($orientation, $unit, $size);
  }

  //If x = -1 and w = 0 then w = page/line width , x = left margin. (Full Line Box)
  //if x = -1 and w > 0 then w = w + margin*2 , x = adjusted to Center box. (Centered Fixed Width Box)
  //if x = -2 and w > 0 then w = w + margin*2 , x = Last X. (Fixed Width Box Starting at Last X)
  //if x = -2 and w = 0 then w = text width + margin*2, x = Last X. (Auto Width Box Starting at Last X)
  //If x >= 0 and w > 0 then w = w + margin*2 , x = unchanged. (Fixed Width Box Starting at X)
  //If x >= 0 and w = 0 then w = text width + margin*2, x = unchanged (Auto Width Box Starting at X)
  function TextBoxSL($x=0, $dy=0, $w=0, $txt='', $align='', $next_item='', 
            $fontsize='', $fontcolour='', $fontdecor='', $font='', 
            $border='', $bgcolour='', $margin=0, $padding=0, $url = '')
  {
    $txt = mb_convert_encoding($txt, 'ISO-8859-1', 'UTF-8');
    $PREV_FONT = $this->FONT;
    $PREV_SIZE = $this->SIZE;
    $PREV_COLOUR = $this->COLOUR;
    $PREV_DECOR = $this->DECOR;
    $PREV_FILLCOL = $this->FillColorHex;
    if ($dy) $this->y += $dy;
    if ($font) $this->FONT = $font;
    if ($fontsize) $this->SIZE = $fontsize;
    if ($fontcolour) $this->COLOUR = $fontcolour;
    if ($fontdecor) $this->DECOR = $fontdecor;
    if ($bgcolour) $this->SetFillColorHex($bgcolour);
    
    $LINE_HEIGHT = $this->_getLineHeight($this->SIZE);

    $this->SetFont($this->FONT,$this->DECOR,$this->SIZE);
    $this->SetTextColorHex($this->COLOUR);

    if (!$w) {
      switch ($x) 
      {
        case -1:
          $this->x = $this->lMargin;
          $w=$this->wc;
          break;
        case -2:
          //x = last x;
          $w=$this->GetStringWidth($txt) + $margin*2;
          break;
        default:
          $this->x = $x;
          $w=$this->GetStringWidth($txt) + $margin*2;
          break;
      }
    } 
    else 
    {
      switch ($x) 
      {
        case -1:
          $this->x = ($this->wc - $w)/2;
          //w = unchanged
          break;
        case -2:
          //x = last x;
          //w = unchanged
          break;
        default:
          $this->x = $x;
          $w = $w + $margin*2;
          break;
      }
    }
    
    switch ($next_item)
    {
      case 'next-inline':
        $nl = 0;
        break;
      case 'next-under':
        $nl = 2;
  //      $next_continue_under = true;
        break;
      case 'next-newline':
        $nl = 1;
        break;
      default:
        $nl = 1; //Moves XY pointer to next line instead of end of block if = 1;
    }

    if ($url) {
      $url = trim($url); //Avoid buggy PDF links
      //Special requirement for HP security error... NM 14 Feb 2012
      if ($url and strpos('/?', $url) === false) $url = str_replace ('?', '/?', $url);
    }
    
    $this->Cell($w, $LINE_HEIGHT + $padding, $txt, $border, $nl, $align, !empty($bgcolour), $url);
    
    $this->FONT = $PREV_FONT;
    $this->SIZE = $PREV_SIZE;
    $this->COLOUR = $PREV_COLOUR;
    $this->DECOR = $PREV_DECOR;
    $this->SetFillColorHex($PREV_FILLCOL);
    if ($next_item == 'next-newline') $this->x = $this->lMargin;
  }

  function TextBoxML($x=0, $dy=0, $w=0, $txt='', $align='', $next_item='', 
            $fontsize='', $fontcolour='', $fontdecor='', $font='', 
            $border='', $fillcolour='', $margin=0)
  {
    $txt = mb_convert_encoding($txt, 'ISO-8859-1', 'UTF-8');
    $left = $this->x;
    $top = $this->y;
    $PREV_FONT = $this->FONT;
    $PREV_SIZE = $this->SIZE;
    $PREV_COLOUR = $this->COLOUR;
    $PREV_DECOR = $this->DECOR;
    $PREV_FILLCOL = $this->FillColorHex;
    if ($dy) { $top += $dy; $this->y = $top; }
    if ($font) $this->FONT = $font;
    if ($fontsize) $this->SIZE = $fontsize;
    if ($fontcolour) $this->COLOUR = $fontcolour;
    if ($fontdecor) $this->DECOR = $fontdecor;
    if ($fillcolour) $this->SetFillColorHex($fillcolour);
    
    $LINE_HEIGHT = $this->_getLineHeight($this->SIZE);

    $this->SetFont($this->FONT,$this->DECOR,$this->SIZE);
    $this->SetTextColorHex($this->COLOUR);

    if (!$w) {
      switch ($x) 
      {
        case -1:
          $this->x = $this->lMargin;
          $w=$this->wc;
          break;
        case -2:
          //x = last x;
          $w=$this->GetStringWidth($txt) + $margin*2;
          break;
        default:
          $this->x = $x;
          $w=$this->GetStringWidth($txt) + $margin*2;
          break;
      }
    } 
    else 
    {
      switch ($x) 
      {
        case -1:
          $this->x = ($this->w - $w)/2;
          //w = unchanged
          break;
        case -2:
          //x = last x;
          //w = unchanged
          break;
        default:
          $this->x = $x;
          $w = $w + $margin*2;
          break;
      }
    }
    
    $this->MultiCell($w, $LINE_HEIGHT, $txt, $border, $align, !empty($fillcolour));
    
    $this->FONT = $PREV_FONT;
    $this->SIZE = $PREV_SIZE;
    $this->COLOUR = $PREV_COLOUR;
    $this->DECOR = $PREV_DECOR;
    $this->SetFillColorHex($PREV_FILLCOL);
    switch ($next_item)
    {
      case 'next-inline':
        $this->x = $left + $w;
        $this->y = $top;
        break;
      case 'next-under':
        $this->x = $left;
        //$this->y = $top + $h;
        break;
      case 'next-newline':
        $this->x = $this->lMargin;
        //$this->y = $top + $h;
        break;
    }

  }

  function TextLine($txt, $dy=0, $align='', $nl='', $fontsize='', $fontcolour='', $fontdecor='', $font='', $border='', $bgcolour='', $margin=0)
  {
    //Full Line Box:
    $this->TextBoxSL(-1, $dy, 0, $txt, $align, $nl, $fontsize, $fontcolour, $fontdecor, $font, $border, $bgcolour, $margin);
  }

  function Bar($x,$y,$w,$h,$colour='',$style='')
  {
    if (!$x) $x = $this->x;
    if (!$y) $y = $this->y;
    if (!$h) $h = 2;
    if (!$w) $w = 2;
    if (!$style) $style = 'F';
    $PREV_FILLCOL = $this->FillColorHex;
    if ($colour) $this->SetFillColorHex($colour);
    $this->Rect($x, $y, $w, $h, $style);
    $this->SetFillColorHex($PREV_FILLCOL);
  }

  function HBar($x=-1,$dy=0,$h='',$colour='',$w_perc=100,$style='')
  {
    $w = $this->wc;
    if ($w_perc) $w = $w * $w_perc/100;
    if ($dy) $this->y += $dy;
    if (!$h) $h = 2;
    if (!$style) $style = 'F';
  //  log_to_file(true,'HBar: page_w='.$this->w.', inside_w='.$this->wc.', bar_w='.$w.', lMargin='.$this->lMargin);
    switch ($x)
    {
      case -1: //Auto Center
        $x = ($this->wc - $w)/2 + $this->lMargin;
        break;
      case -2: //Right Align;
        $x = $this->rMargin - $w;
        break;
      default:
        $x += $this->lMargin;
    }
    $PREV_FILLCOL = $this->FillColorHex;
    if ($colour) $this->SetFillColorHex($colour);
    $this->Rect($x, $this->y, $w, $h, $style);
    $this->y += $h;
    $this->lasth = $h;
    $this->SetFillColorHex($PREV_FILLCOL);
  }

  function Frame($dx=0,$dy=0,$w=0,$h=0,$frame_thickness=0,$frame_colour='',$next_item='')
  {
    $left = $this->x;
    $top = $this->y;
    if ($dx) $left += $dx;
    if ($dy) $top += $dy;
    if (!$w) $w = 20;
    if (!$h)  $h = 20;
    $PREV_LINE_WIDTH = $this->LineWidth;
    if ($frame_thickness) $this->SetLineWidth($frame_thickness); else $frame_thickness = $this->LineWidth;
    $dh = $frame_thickness / 2;
    $PREV_DRAWCOL = $this->DrawColorHex;
    if ($frame_colour) $this->SetDrawColorHex($frame_colour);
    $this->Rect($left+$dh, $top+$dh, $w-$frame_thickness, $h-$frame_thickness);
    switch ($next_item)
    {
      case 'next-inline':
        $this->x = $left + $w;
        $this->y = $top;
        break;
      case 'next-under':
        $this->x = $left;
        $this->y = $top + $h;
        break;
      case 'next-newline':
        $this->x = $this->lMargin;
        $this->y = $top + $h;
        break;
      case 'next-inside':
        $this->SetXY($left+$frame_thickness,$top+$frame_thickness);
        break;
    } 
    $this->SetLineWidth($PREV_LINE_WIDTH);
    $this->SetDrawColorHex($PREV_DRAWCOL);
  }

  function Image($file,$dx=0,$dy=0,$w=0,$h=0,$frame_thickness=0,$frame_colour='',$next_item='',$type='',$link='')
  {
    $left = $this->x;
    $top = $this->y;
    if ($dx) $left += $dx;
    if ($dy) $top += $dy;
    if (!$w) $w = 20;
    if (!$h)  $h = 20;
    $PREV_LINE_WIDTH = $this->LineWidth;
    if ($frame_thickness) $this->SetLineWidth($frame_thickness); else $frame_thickness = 0;
    $dh = $frame_thickness / 2;
    $PREV_DRAWCOL = $this->DrawColorHex;
    if ($frame_colour) $this->SetDrawColorHex($frame_colour);
    if ($frame_thickness) $this->Rect($left+$dh, $top+$dh, $w-$frame_thickness, $h-$frame_thickness);
    $dh = $frame_thickness*2;
    parent::Image($file,$left+$frame_thickness,$top+$frame_thickness,$w-$dh,$h-$dh,$type,$link);
    switch ($next_item)
    {
      case 'next-inline':
        $this->x = $left + $w;
        $this->y = $top;
        break;
      case 'next-under':
        $this->x = $left;
        $this->y = $top + $h;
        break;
      case 'next-newline':
        $this->x = $this->lMargin;
        $this->y = $top + $h;
        break;
    } 
    $this->SetLineWidth($PREV_LINE_WIDTH);
    $this->SetDrawColorHex($PREV_DRAWCOL);
  }

  function GetMargin($side='left')
  {
    switch ($side)
    {
      case 'left': return $this->lMargin;
      case 'right': return $this->rMargin;
      case 'top': return $this->tMargin;
      case 'bottom': return $this->bMargin;
    }
  }

  function SetMargin($side='left', $margin=0)
  {
    switch ($side)
    {
      case 'left': $this->lMargin = $margin; break;
      case 'right': $this->rMargin = $margin; break;
      case 'top': $this->tMargin = $margin; break;
      case 'bottom': $this->bMargin = $margin; break;
    }
    $this->wc = $this->w - $this->lMargin - $this->rMargin;
  }

  function SetMargins($left=null, $top=null, $right=null)
  {
    if ($left ) $this->lMargin = $left;
    if ($top  ) $this->tMargin = $top;
    if ($right) $this->rMargin = $right;
    $this->wc = $this->w - $this->lMargin - $this->rMargin;
  }

  function SetTextColorHex($HexColourStr='#000000')
  {
    $len = strlen($HexColourStr);
    if ($len < 6 || $len > 7) return;
    if ($len == 7) $HexColourStr = substr($HexColourStr,1);
    $r = substr($HexColourStr,0,2);
    $g = substr($HexColourStr,2,2);
    $b = substr($HexColourStr,4,2);
    //echo 'rgb = ('.$r.','.$g.','.$b.')'.BR; 
    $this->SetTextColor(hexdec($r),hexdec($g),hexdec($b));
  }

  function SetFillColor($r, $g=null, $b=null)
  {
    parent::SetFillColor($r,$g,$b);
    $this->FillColorHex = sprintf('#%2x%2x%2x',$r,$g,$b);
  }

  function SetFillColorHex($HexColourStr='#FFFFFF')
  {
    $len = strlen($HexColourStr);
    if ($len < 6 || $len > 7) return;
    if ($len == 7) $HexColourStr = substr($HexColourStr,1);
    $r = substr($HexColourStr,0,2);
    $g = substr($HexColourStr,2,2);
    $b = substr($HexColourStr,4,2);
    //echo 'rgb = ('.$r.','.$g.','.$b.')'.BR; 
    $this->SetFillColor(hexdec($r),hexdec($g),hexdec($b));
  }

  function SetDrawColor($r, $g=null, $b=null)
  {
    parent::SetDrawColor($r,$g,$b);
    $this->DrawColorHex = sprintf('#%2x%2x%2x',$r,$g,$b);
  }

  function SetDrawColorHex($HexColourStr='#FFFFFF')
  {
    $len = strlen($HexColourStr);
    if ($len < 6 || $len > 7) return;
    if ($len == 7) $HexColourStr = substr($HexColourStr,1);
    $r = substr($HexColourStr,0,2);
    $g = substr($HexColourStr,2,2);
    $b = substr($HexColourStr,4,2);
    //echo 'rgb = ('.$r.','.$g.','.$b.')'.BR; 
    $this->SetDrawColor(hexdec($r),hexdec($g),hexdec($b));
  }

  function SaveStyle()
  {
    $this->PREV_FONT = $this->FONT;
    $this->PREV_SIZE = $this->SIZE;
    $this->PREV_COLOUR = $this->COLOUR;
    $this->PREV_DECOR = $this->DECOR;
  }

  function SetStyle(array $style)
  {
    if (isset($style['FONT']))  $this->FONT   = $style['FONT'];
    if (isset($style['SIZE']))  $this->SIZE   = $style['SIZE'];
    if (isset($style['COLOR'])) $this->COLOUR = $style['COLOR'];
    if (isset($style['DECOR'])) $this->DECOR  = $style['DECOR'];
    $this->SetFont($this->FONT,$this->DECOR,$this->SIZE);
    $this->SetTextColorHex($this->COLOUR);
    $this->LINE_HEIGHT = $this->_getLineHeight($this->SIZE);
    //echo "FONT = ".$this->FONT.' , DECOR = '.$this->DECOR.' , SIZE = '.$this->SIZE.BR;
    //echo 'LINE HEIGHT = '.$this->LINE_HEIGHT.BR;
  }

  function RestoreStyle()
  {
    $this->FONT = $this->PREV_FONT;
    $this->SIZE = $this->PREV_SIZE;
    $this->COLOUR = $this->PREV_COLOUR;
    $this->DECOR = $this->PREV_DECOR;
    $this->SetFont($this->FONT,$this->DECOR,$this->SIZE);
    $this->SetTextColorHex($this->COLOUR);
    $this->LINE_HEIGHT = $this->_getLineHeight($this->SIZE);
  }

  function OpenTag($tag, $attr)
  {
    switch ($tag)
    {
      case 'A' : $this->HREF = $attr['HREF']; break;
      case 'S' : $this->SaveStyle(); $this->SetStyle($attr); break;
      case 'BR': $this->Ln($this->LINE_HEIGHT); break;
    }
  }

  function CloseTag($tag)
  {
    // Closing tag
    switch ($tag)
    {
      case 'S': $this->RestoreStyle(); break;
      case 'A': $this->HREF = ''; break;
    }
  }

  function PutLink($URL, $txt, $dx=0, $dy=0, $w=0, $next_item = 'next-newline', $align='L', $font_size=9)
  {
    // Put a hyperlink
    $this->x += $dx;
    $this->y += $dy;
    switch ($next_item)
    {
      case 'next-inline': $nl = 0; break;
      case 'next-under': $nl = 2; break;
      case 'next-newline':
      default: $nl = 1;
    }
    $this->SetTextColor(0,0,255);
    $this->SetFont('', 'U', $font_size);
    if (!$w) $w = $this->GetStringWidth($txt)+2;
    $URL = trim($URL); //Also remove spaces before/after link to avoid errors on PDF report
    if (strpos('/?', $URL) === false) $URL = str_replace ('?', '/?', $URL); //Special requirement for HP security error... NM 14 Feb 2012
    $this->Cell($w, $this->LINE_HEIGHT, $txt, 0, $nl, $align, 0, $URL);
    $this->SetFont('', $this->DECOR);
    $this->SetTextColorHex($this->COLOUR);
  }  

  function WriteMarkup($x=0, $y=0, $w=0, $markup_text='', $align='L')
  {
    $LM = $this->lMargin;
    $RM = $this->rMargin;

    if ($x) $this->x = $x;
    if ($y) $this->y = $y;

    if ($w) {
      if ($w > $this->wc) $w = $this->wc;
      switch ($align)
      {
        case 'C': $this->x = ($this->wc - $w)/2; break;
        case 'R': $this->x = $this->wc - $w; break;
      }
    }

    $this->lMargin = $this->x;
    $this->rMargin = $this->w - $this->lMargin - $this->x - $w;

    // Markup Parser
    $markup_text = str_replace("\n",' ',$markup_text);
    $a = preg_split('/<(.*)>/U',$markup_text,-1,PREG_SPLIT_DELIM_CAPTURE);
    foreach ($a as $i=>$e)
    {
      if ($i%2)
      {   // Tag
        // E.g. <S FONT=Arial SIZE=8 COLOR=#FFFFF DECOR=B> TEXT </S>
        // E.g  <A HREF="http://"> TEXT </A>
        // E.g  <BR>

        if($e[0] != '/')
        {
          $a2 = explode(' ',$e);
          $tag = strtoupper(array_shift($a2));
          $attr = array();
          foreach($a2 as $v)
          {
            $attr_parts = explode('=', $v);
            if (isset($attr_parts[1])) $attr[strtoupper($attr_parts[0])] = $attr_parts[1];
          }
          switch ($tag)
          {
            case 'A' : $this->HREF = $attr['HREF']; break;
            case 'S' : $this->SaveStyle(); $this->SetStyle($attr); break;
            case 'BR': $this->Ln($this->LINE_HEIGHT); break;
          }
        }
        else
        {
          $tag = strtoupper(substr($e,1));
          switch ($tag)
          {
            case 'S': $this->RestoreStyle(); break;
            case 'A': $this->HREF = ''; break;
          }
        }
      }
      else 
      {   // Text
        if ($e == '') continue;
        if ($this->HREF) $this->PutLink($this->HREF,$e);  else $this->Write($this->LINE_HEIGHT,$e);
      }
    }//end: foreach
    
    $this->lMargin = $LM;
    $this->rMargin = $RM;
  }

  function MarkupLine($x=0, $y=0, $markup_text='', $align='L', $next_item='')
  {
    if (!$markup_text) return false;

    $w = 0;
    $h = 0;

    $this->SaveStyle();

    // Markup Parser
    $markup_text = str_replace("\n",' ',$markup_text);
    $a = preg_split('/<(.*)>/U',$markup_text,-1,PREG_SPLIT_DELIM_CAPTURE);
    $n = 0;
    $output = array();
    foreach ($a as $i=>$e)
    {
      if ($i%2)
      {   // Tag
        // E.g. <S FONT=Arial SIZE=8 COLOR=#FFFFF DECOR=B> TEXT </S>
        // E.g  <A HREF="http://"> TEXT </A>
        // E.g  <BR>
        if($e[0] != '/')
        {
          //Open TAG
          $a2 = explode(' ',$e);
          $tag = strtoupper(array_shift($a2));
          $attr = array();
          foreach($a2 as $v)
          {
            $attr_parts = explode('=', $v);
            if (isset($attr_parts[1])) $attr[strtoupper($attr_parts[0])] = $attr_parts[1];
          }
          switch ($tag)
          {
            case 'A' : $this->HREF = $attr['HREF']; break;
            case 'S' : $this->SetStyle($attr); break;
            case 'BR': break;
          }
        }
        else
        {
          //Close Tag
          $tag = strtoupper(substr($e,1));
          switch ($tag)
          {
            case 'S' : break;
            case 'A' : $this->HREF = ''; break;
            case 'BR': break;
          }
        }
      }
      else
      {   // Text
        if ($e == '') continue;
        $ew = $this->GetStringWidth($e);
        $eh = $this->LINE_HEIGHT;
        $w += $ew;
        if ($eh > $h) $h = $eh;
        $output[$n]['TEXT']   = $e;
        $output[$n]['WIDTH']  = $ew;
        $output[$n]['HEIGHT'] = $eh;
        $output[$n]['HREF']   = $this->HREF;
        $output[$n]['FONT']   = $this->FONT;
        $output[$n]['SIZE']   = $this->SIZE;
        $output[$n]['COLOR']  = $this->COLOUR;
        $output[$n]['DECOR']  = $this->DECOR;
        $n++;
      }
    }//end: foreach

    if ($y) $this->y = $y; else $y = $this->y;

    if ($x) $this->x = $x; else {

      switch ($align)
      {
        case 'L': break;
        case 'R': $this->x = $this->wc - $w; break;
        case 'C': $this->x = ($this->wc - $w)/2; break;
      }
      $x = $this->x;
    }

    foreach ($output as $e)
    {
      $this->SetStyle($e);
      if ($this->HREF)
      {
        $this->Cell($e['WIDTH'], $e['HEIGHT'], $e['TEXT'], 0, 0, null, null, $this->HREF);
      }
      else
      {
        $this->Cell($e['WIDTH'], $e['HEIGHT'], $e['TEXT'], 0, 0);
      }
      $this->RestoreStyle();
    }

    switch ($next_item)
    {
      case 'next-under'  : $this->x = $x; break;
      case 'next-newline': $this->x = $this->lMargin; $this->y = $y + $h; break;
      case 'next-inline' : $this->y = $y + $h; break;
    }

  } // MarkupLine

} // PDF
