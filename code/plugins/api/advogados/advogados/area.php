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
 * Area API Resource (Lawyers by specific practice area)
 *
 * @since  1.0.0
 */
class AdvogadosApiResourceArea extends ApiResource
{
	/**
	 * Handle GET requests - Get lawyers by practice area
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
			$areaId = $app->input->get('id', 0, 'INT');
			$limit = $app->input->get('limit', 0, 'INT'); // Default: no limit
			$offset = $app->input->get('offset', 0, 'INT');

			// Detect language from URI like other endpoints
			$uri = Uri::getInstance();
			$segments = explode('/', trim($uri->getPath(), '/'));
			$currentLanguage = 'pt-BR'; // Default
			
			if (in_array('en', $segments)) {
				$currentLanguage = 'en-GB';
			} elseif (in_array('pt', $segments)) {
				$currentLanguage = 'pt-BR';
			}

			if (empty($areaId)) {
				$this->plugin->setResponse([
					'error' => true,
					'message' => 'Area ID is required',
					'code' => 400
				]);
				return;
			}

			// Check if the requested area is excluded (same logic as helper)
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
					// Check if requested area is excluded
					$db = Factory::getDbo();
					$checkQuery = $db->getQuery(true);
					$checkQuery->select('type_area')
						->from('#__advogados_area_atuacao')
						->where('id = ' . (int) $areaId);
					$db->setQuery($checkQuery);
					$areaType = $db->loadResult();
					
					if ($areaType && in_array((int) $areaType, $excludedTypesArray)) {
						$this->plugin->setResponse([
							'error' => true,
							'message' => 'This practice area is not available through the API',
							'code' => 403
						]);
						return;
					}
				}
			}

			// Get data using custom query with language filtering
			$db = Factory::getDbo();
			$query = $db->getQuery(true);
			
			$query->select('DISTINCT a.*')
				->from('#__advogados AS a')
				->where('a.state = 1') // Only published items
				->where('(a.area_atuacao LIKE ' . $db->quote('%,' . (int) $areaId . ',%') . 
						' OR a.area_atuacao LIKE ' . $db->quote((int) $areaId . ',%') . 
						' OR a.area_atuacao LIKE ' . $db->quote('%,' . (int) $areaId) . 
						' OR a.area_atuacao = ' . $db->quote((string) $areaId) . ')');

			// Apply language filtering following Joomla 5 native standards
			$query->where('(a.linguagem = ' . $db->quote($currentLanguage) . ' OR a.linguagem = ' . $db->quote('*') . ')');

			// Apply ordering
			$query->order('a.nome ASC');

			// Apply limit and offset
			if ($limit > 0) {
				$query->setLimit($limit, $offset);
			}

			$db->setQuery($query);
			$items = $db->loadObjectList();
			
			// Validate items before processing
			if (!is_array($items)) {
				$items = [];
			}

			$helper = new AdvogadosApiHelper();
			$advs = $helper->trataItensAdvogados($items);

			$this->plugin->setResponse($advs);
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
