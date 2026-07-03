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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Component\ComponentHelper;
use Advogados\Component\Advogados\Site\Model\AdvogadosModel;

/**
 * Areas API Resource (List all practice areas)
 *
 * @since  1.0.0
 */
class AdvogadosApiResourceAreas extends ApiResource
{
	/**
	 * Handle GET requests - List all practice areas
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @throws  Exception
	 */
	public function get(): void
	{
		try {
			// Initialize variables
			$app = Factory::getApplication();
			$lang = $app->input->get('lang', '', 'STRING');
			$id = $app->input->get('id', 0, 'INT');
			$nome = $app->input->get('nome', '', 'STRING');
			$area = $app->input->get('area', '', 'STRING'); // Alias for nome

			// Use area as alias for nome if nome is empty
			if (empty($nome) && !empty($area)) {
				$nome = $area;
			}

			// Use Joomla's language detection directly
			$currentLanguage = 'pt-BR'; // Default
			
			// Check explicit lang parameter
			if ($lang === 'en') {
				$currentLanguage = 'en-GB';
			} elseif ($lang === 'pt') {
				$currentLanguage = 'pt-BR';
			} else {
				// Try to detect from Joomla's current language
				$joomlaLang = Factory::getLanguage();
				$tag = $joomlaLang->getTag();
				if ($tag === 'en-GB') {
					$currentLanguage = 'en-GB';
				} else {
					$currentLanguage = 'pt-BR';
				}
			}

			// Get database connection directly for consistent language handling
			$db = Factory::getDbo();
			$query = $db->getQuery(true);

			// Select all practice areas with language filtering
			$query->select('*')
				->from('#__advogados_area_atuacao')
				->where('state = 1')
				->where('linguagem = ' . $db->quote($currentLanguage));

			// Add ID filter
			if ($id > 0) {
				$query->where('id = ' . (int) $id);
			}

			// Add name filter (partial match)
			if (!empty($nome)) {
				$query->where('area_atuacao LIKE ' . $db->quote('%' . $nome . '%'));
			}

			// Exclude specific type_area values (same as helper)
			$pluginparams = new Registry($this->plugin->params);
			$excludedTypes = $pluginparams->get('excluded_type_areas', '3');
			if (!empty($excludedTypes)) {
				// Handle multiple values (comma-separated or array)
				if (is_string($excludedTypes)) {
					$excludedTypesArray = array_filter(array_map('intval', explode(',', $excludedTypes)));
				} else {
					$excludedTypesArray = array_filter(array_map('intval', (array) $excludedTypes));
				}
				
				if (!empty($excludedTypesArray)) {
					$query->where('type_area NOT IN (' . implode(',', $excludedTypesArray) . ')');
				}
			}

			$query->order('area_atuacao ASC');

			$db->setQuery($query);
			$areas = $db->loadObjectList();

			$this->plugin->setResponse([
				'error' => false,
				'message' => 'Practice areas retrieved successfully',
				'data' => $areas ?: []
			]);
		} catch (Exception $e) {
			$this->plugin->setResponse([
				'error' => true,
				'message' => $e->getMessage(),
				'code' => $e->getCode() ?: 500
			]);
		}
	}

	/**
	 * Handle POST requests
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function post(): void
	{
		$this->plugin->setResponse([
			'error' => true,
			'message' => 'POST method not implemented for this resource',
			'code' => 405
		]);
	}
}
