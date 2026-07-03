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
use Advogados\Component\Advogados\Site\Model\AdvogadosModel;

/**
 * Advogados API Resource (Multiple lawyers)
 *
 * @since  1.0.0
 */
class AdvogadosApiResourceAdvogados extends ApiResource
{
	/**
	 * Handle GET requests for multiple lawyers
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
			$limit = $app->input->get('limit', 0, 'INT'); // Default: no limit
			$offset = $app->input->get('offset', 0, 'INT');
			$nome = $app->input->get('nome', '', 'STRING');
			$area = $app->input->get('area', '', 'STRING');
			$local = $app->input->get('local', '', 'STRING');
			$tipo = $app->input->get('tipo', '', 'STRING');
			$lang = $app->input->get('lang', '', 'STRING');
			$id = $app->input->get('id', 0, 'INT');
			$codigo = $app->input->get('codigo', '', 'STRING');
			$sigla = $app->input->get('sigla', '', 'STRING');
			$sigla = $app->input->get('sigla', '', 'STRING');

			// Get the model using namespace
			$advModel = new AdvogadosModel();

			if (!$advModel) {
				throw new Exception('Failed to load Advogados model', 500);
			}

			$helper = new AdvogadosApiHelper();

			// Use custom query for better control over filtering
			$db = Factory::getDbo();
			$query = $db->getQuery(true);
			
			$query->select('DISTINCT a.*')
				->from('#__advogados AS a')
				->where('a.state = 1'); // Only published items

			// Add language filter
			if (!empty($lang)) {
				if ($lang === 'pt') {
					$query->where('a.linguagem = ' . $db->quote('pt-BR'));
				} elseif ($lang === 'en') {
					$query->where('a.linguagem = ' . $db->quote('en-GB'));
				}
			}

			// Add ID filter
			if ($id > 0) {
				$query->where('a.id = ' . (int) $id);
			}

			// Add codigo/sigla filter (both parameters work for the same field)
			if (!empty($codigo)) {
				$query->where('a.codigo = ' . $db->quote($codigo));
			} elseif (!empty($sigla)) {
				$query->where('a.codigo = ' . $db->quote($sigla));
			}

			// Add filters if provided
			if (!empty($area)) {
				$query->where('a.area_atuacao = ' . (int) $area);
			}
			if (!empty($nome)) {
				$query->where('a.nome LIKE ' . $db->quote('%' . $nome . '%'));
			}
			if (!empty($local)) {
				$query->where('a.local = ' . (int) $local);
			}
			if (!empty($tipo)) {
				$query->where('a.tipo = ' . (int) $tipo);
			}

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
