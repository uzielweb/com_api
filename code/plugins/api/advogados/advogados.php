<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  API.Advogados
 *
 * @copyright   Copyright (C) 2024 Machado Meyer. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;

/**
 * API Plugin para Advogados
 *
 * @since  1.0.0
 */
class plgAPIAdvogados extends ApiPlugin
{
	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings
	 *
	 * @since   1.0.0
	 */
	public function __construct(&$subject, $config = [])
	{
		parent::__construct($subject, $config);

		// Load helper file
		$helperPath = JPATH_SITE . '/plugins/api/advogados/helper/advogadoshelper.php';
		if (file_exists($helperPath)) {
			require_once $helperPath;
		}

		// Add resource include path
		ApiResource::addIncludePath(dirname(__FILE__) . '/advogados');

		// Load component language
		$this->loadComponentLanguage();

		// Set resources & access
		$this->setResourceAccess('advogado', 'public', 'get');
		$this->setResourceAccess('advogados', 'public', 'get');  // Múltiplos advogados
		$this->setResourceAccess('areas', 'public', 'get');
		$this->setResourceAccess('area', 'public', 'get');       // Área específica
	}

	/**
	 * Load component language files
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function loadComponentLanguage(): void
	{
		try {
			$lang = Factory::getLanguage();
			$extension = 'com_advogados';
			$baseDir = JPATH_ADMINISTRATOR;
			$languageTag = 'pt-BR';
			$reload = true;
			
			$lang->load($extension, $baseDir, $languageTag, $reload);
		} catch (Exception $e) {
			// Log error but don't break execution
			Factory::getApplication()->enqueueMessage(
				Text::sprintf('PLG_API_ADVOGADOS_ERROR_LOADING_LANGUAGE', $e->getMessage()),
				'warning'
			);
		}
	}
}
