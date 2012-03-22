<?php
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');
 
jimport('joomla.form.formfield');
 
// The class name must always be the same as the filename (in camel case)
class JFormFieldAsset extends JFormField {
 
	//The field class must know its own type through the variable $type.
	protected $type = 'Asset';
 
 
	public function getInput() {
		// code that returns HTML that will be shown as the form field
		$db = JFactory::getDBO();
		$query = $db->getQuery(true);
		
		$query->select('template');
		$query->from($db->nameQuote('#__template_styles'));
		$query->where('home = '.(int) 1);
		$query->where('client_id = '.(int) 0);
		
		$db->setQuery($query);
		
		$templateName = $db->loadResult();
		
		jimport( 'joomla.filesystem.folder' );
		$directories = JFolder::folders(JPATH_SITE.DS.'templates'.DS.$templateName);
		
		$selectOptions = array();
		foreach($directories as $directory){
			$selectOptions[] = array('value' => $directory);
		}
		
		return JHTML::_('select.genericlist', $selectOptions, $this->name.'[]', 'class="inputbox" multiple size="8" style="width:150px"', 'value', 'value', $this->value );
	}
}