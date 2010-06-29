<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Winans Creative 2009, Intelligent Spark 2010, iserv.ch GmbH 2010
 * @author     Fred Bliss <fred.bliss@intelligentspark.com>
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


class ProductsWizard extends Widget
{

	/**
	 * Submit user input
	 * @var boolean
	 */
	protected $blnSubmitInput = true;

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_widget';
	
	/**
	 * Products
	 * @var array
	 */
	protected $arrProducts = false;


	/**
	 * Make sure we know the ID for ajax upload session data
	 * @param array
	 */
	public function __construct($arrAttributes=false)
	{
		$this->strId = $arrAttributes['id'];
		$_SESSION['AJAX-FFL'][$this->strId] = array('type'=>'fancyupload');
		
		parent::__construct($arrAttributes);
		
		$this->import('Database');
	}
	
	
	/**
	 * Store config for ajax upload.
	 * 
	 * @access public
	 * @param string $strKey
	 * @param mixed $varValue
	 * @return void
	 */
	public function __set($strKey, $varValue)
	{
		$_SESSION['AJAX-FFL'][$this->strId][$strKey] = $varValue;
		
		switch ($strKey)
		{
			case 'products':
				$this->arrProducts = deserialize($varValue);
				break;
				
			case 'mandatory':
				$this->arrConfiguration['mandatory'] = $varValue ? true : false;
				break;

			default:
				parent::__set($strKey, $varValue);
				break;
		}
	}
	
	
	/**
	 * Validate input and set value
	 */
	public function validator($varInput)
	{
		if (!is_array($varInput) || !count($varInput))
		{
			$this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['mandatory'], $this->strLabel));
		}
		
		return $varInput;
	}

	
	
	/**
	 * Generate the widget and return it as string
	 * @return string
	 */
	public function generate()
	{
		$this->loadLanguageFile('tl_iso_products');
		
		$arrIds = deserialize($this->varValue);
		
		if (!is_array($arrIds) || !count($arrIds))
		{
			$arrIds = array(0);
		}
				
		// User has javascript disabled an clicked on link
		if ($this->Input->get('noajax'))
		{
			$strProducts = $this->listProducts($this->Database->execute("SELECT * FROM tl_iso_products WHERE pid=0 ORDER BY id=" . implode(' DESC, id=', $arrIds) . " DESC, name"));
		}
		else
		{
			$strProducts = $this->listProducts($this->Database->execute("SELECT * FROM tl_iso_products WHERE pid=0 AND id IN (" . implode(',', $arrIds) . ")"));
			
			$strProducts .= '
    <tr class="jserror">
      <td colspan="3"><a href="' . $this->addToUrl('noajax=1') . '">Please turn on Javascript in your browser. Click here to use the Javascript fallback version.</a></td>
    </tr>
    <tr class="search" style="display:none">
      <td colspan="3"><label for="ctrl_' . $this->strId . '_search">Search products:</label> <input type="text" id="ctrl_' . $this->strId . '_search" name="keywords" class="tl_text" autocomplete="off" /></td>
    </tr>';
		}
		
		
		return '
<table cellspacing="0" cellpadding="0" id="ctrl_' . $this->strId . '" class="tl_productswizard" summary="Products">
  <thead>
    <tr>
      <th class="head_0 col_first">&nbsp;</th>
  	  <th class="head_1">' . $GLOBALS['TL_LANG']['tl_iso_products']['type'][0] . '</th>
  	  <th class="head_2">' . $GLOBALS['TL_LANG']['tl_iso_products']['name'][0] . '</th>
  	  <th class="head_3 col_last">' . $GLOBALS['TL_LANG']['tl_iso_products']['sku'][0] . '</th>
    </tr>
  </thead>
  <tbody>
' . $strProducts . '
  </tbody>
</table>
<script type="text/javascript">
<!--//--><![CDATA[//><!--' . "
window.addEvent('domready', function() {
  Isotope.productWizard('" . $this->strId . "');
});
" . '//--><!]]>
</script>';
	}
	
	
	public function generateAjax()
	{
		$arrKeywords = trimsplit(' ', $this->Input->post('keywords'));

		$strFilter = '';
		$arrProcedures = array();
		$arrValues = array();
		
		foreach( $arrKeywords as $keyword )
		{
			if (!strlen($keyword))
				continue;
				
			$arrProcedures[] .= "name LIKE ? OR alias LIKE ? OR sku LIKE ? OR description LIKE ?";
			$arrValues[] = '%'.$keyword.'%';
			$arrValues[] = '%'.$keyword.'%';
			$arrValues[] = '%'.$keyword.'%';
			$arrValues[] = '%'.$keyword.'%';
		}
		
		if (!count($arrProcedures))
			return '';
		
		$arrProducts = $this->Input->post($this->strName);
		if (is_array($arrProducts) && count($arrProducts))
		{
			$strFilter = ") AND id NOT IN (" . implode(',', $arrProducts);
		}
		
		$objProducts = $this->Database->prepare("SELECT * FROM tl_iso_products WHERE pid=0 AND (" . implode(' OR ', $arrProcedures) . $strFilter . ")")
									  ->execute($arrValues);
									  
		$strBuffer = $this->listProducts($objProducts, true);
		
		if (!strlen($strBuffer))
			return '<tr class="found empty"><td colspan="3">No (more) products matching "' . $this->Input->post('keywords') . '" found.</td></tr>';
			
		return $strBuffer;
	}
	
	
	protected function listProducts($objProducts, $blnAjax=false)
	{
		$c=0;
		$strProducts = '';
		
		while( $objProducts->next() )
		{
			if (is_array($this->arrProducts) && !in_array($objProducts->id, $this->arrProducts))
				continue;
				
			$strProducts .= '
    <tr class="' . ($c%2 ? 'even' : 'odd') . ($c==0 ? ' row_first' : '') . ($blnAjax ? ' found' : '') . '">
      <td class="col_0 col_first"><input type="checkbox" class="checkbox" name="' . $this->strId . '[]" value="' . $objProducts->id . '"' . ($blnAjax ? '' : ' checked="checked"') . ' /></td>
      <td class="col_1">' . $this->Database->prepare("SELECT name FROM tl_iso_producttypes WHERE id=?")->execute($objProducts->type)->name . '</td>
      <td class="col_2">' . $objProducts->name . '</td>
      <td class="col_3 col_last">' . $objProducts->sku . '</td>
    </tr>';
    		
    		$c++;
		}
		
		return $strProducts;
	}
}

