<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  API.ArticlesIJ
 *
 * @copyright   Copyright (C) 2024 Machado Meyer. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Site\Helper\RouteHelper;


class ArticlesijApiResourceLatest extends ApiResource
{
	
	public function get()
	{
		$this->plugin->setResponse($this->getLatest());
	}
	//get latest article
	public function getLatest()
	{
		// Get the dbo
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$result = new stdClass();
		$app = Factory::getApplication();
		
		// Get language parameter
		$lang = $app->input->get('lang', 'pt', 'STRING');
		$languageCode = $lang === 'en' ? 'en-GB' : 'pt-BR';

		// Get pagination parameters
		$offset = $app->input->get('offset', 0, 'int');
		$limitstart = $app->input->get('limitstart', $offset, 'int'); // backward compatibility
		$categoryId = $app->input->get('categoryId', 0, 'int');

		// Handle limit with nolimit support
		$nolimit = $app->input->get('nolimit', 0, 'INT');
		if ($nolimit == 1) {
			$limit = 0; // No limit
		} else {
			$limit = $app->input->get('limit', 0, 'int'); // Default: no limit
		}

		try {
			// Busca direta no banco de dados - mais simples e compatível com Joomla 5
			// Filtrar apenas artigos da categoria "Inteligência Jurídica" (presumindo que seja o propósito do articlesij)
			$query = $db->getQuery(true)
				->select('a.id, a.title, a.alias, a.introtext, a.fulltext, a.created, a.publish_up, a.catid, a.access')
				->select('c.title AS category_title, c.alias AS category_alias')
				->from('#__content AS a')
				->join('LEFT', '#__categories AS c ON c.id = a.catid')
				->where('a.state = 1')
				->where('a.publish_up <= NOW()')
				->where('(a.publish_down IS NULL OR a.publish_down >= NOW())')
				->where('(a.language = ' . $db->quote($languageCode) . ' OR a.language = ' . $db->quote('*') . ')')
				->order('a.publish_up DESC');

			// Filter by category if specified
			if ($categoryId > 0) {
				$query->where('a.catid = ' . (int) $categoryId);
			}

			// Apply limit and offset
			if ($limit > 0) {
				$db->setQuery($query, $limitstart, $limit);
			} else {
				$db->setQuery($query);
			}

			$items = $db->loadObjectList();

			if ($items) {
				foreach ($items as $item) {
					// Criar slug completo
					$item->slug = $item->id . ':' . $item->alias;
					$item->catslug = $item->catid . ':' . $item->category_alias;
					
					// Adicionar link do artigo
					$item->link = 'index.php?option=com_content&view=article&id=' . $item->slug . '&catid=' . $item->catslug;
				}
				
				$result->success = 1;
				$result->data = $items;
			} else {
				$result->success = 0;
				$result->data = [];
				$result->message = 'No articles found';
			}
		} catch (Exception $e) {
			$result->success = 0;
			$result->data = [];
			$result->message = 'Error fetching articles: ' . $e->getMessage();
		}

		return $result;
	}
	
	public function post()
	{  
		$this->plugin->setResponse("Use get method");
	}
}
