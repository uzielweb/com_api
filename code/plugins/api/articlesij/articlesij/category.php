<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  API.Articlesij
 *
 * @copyright   Copyright (C) 2024 Machado Meyer. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

/**
 * Articles IJ Categories Resource
 */
class ArticlesijApiResourceCategory extends ApiResource
{
	public function get()
	{
		$this->plugin->setResponse($this->getCategory());
	}
	
	public function getCategory()
	{
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$app = Factory::getApplication();
		$result = new stdClass();
		
		// Get parameters
		$catid = $app->input->get('id', 0, 'INT');
		$lang = $app->input->get('lang', 'pt', 'STRING');
		
		// Get pagination parameters
		$limit = $app->input->get('limit', 0, 'int'); // Default: no limit
		$offset = $app->input->get('offset', 0, 'int');
		$limitstart = $app->input->get('limitstart', $offset, 'int'); // backward compatibility
		
		// Get language code for filtering
		$languageCode = $lang === 'en' ? 'en-GB' : 'pt-BR';

		try {
			if ($catid) {
				// Get IJ articles from specific category - includes parent category 137 and subcategories
				$subquery = $db->getQuery(true)
					->select('sub.id')
					->from('#__categories AS sub')
					->join('INNER', '#__categories AS this ON sub.lft > this.lft AND sub.rgt < this.rgt')
					->where('this.id = 137')
					->where('sub.level <= this.level + 1');

				$query = $db->getQuery(true)
					->select('a.id, a.title, a.alias, a.introtext, a.fulltext, a.created, a.publish_up, a.catid, a.access')
					->select('c.title AS category_title, c.alias AS category_alias')
					->from('#__content AS a')
					->join('LEFT', '#__categories AS c ON c.id = a.catid')
					->where('a.state = 1')
					->where('c.published = 1')
					->where('a.publish_up <= NOW()')
					->where('(a.publish_down IS NULL OR a.publish_down >= NOW())')
					->where('(a.catid = 137 OR a.catid IN (' . $subquery . '))')
					->where('(a.language = ' . $db->quote($languageCode) . ' OR a.language = ' . $db->quote('*') . ')')
					->order('a.publish_up DESC');

				// If specific category requested, filter by it
				if ($catid != 137) {
					$query->where('a.catid = ' . (int) $catid);
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
						$item->link = 'index.php?option=com_content&view=article&id=' . $item->slug . '&catid=' . $item->catslug;

						// Add custom fields
						try {
							if (class_exists('FieldsHelper')) {
								$fields = FieldsHelper::getFields('com_content.article', ['id' => $item->id]);
								foreach ($fields as $field) {
									if ($field->name == 'imagem-do-cabecalho') {
										$item->imagem_do_cabecalho = $field->value;
									}
								}
							}
						} catch (Exception $e) {
							// Fields not available
						}
					}
				}

				$result->success = 1;
				$result->data = $items;
			} else {
				// Get IJ categories (parent 137 and its subcategories)
				$query = $db->getQuery(true)
					->select('c.id, c.title, c.alias, c.description, c.published, c.parent_id')
					->select('COUNT(a.id) AS article_count')
					->from('#__categories AS c')
					->join('LEFT', '#__content AS a ON a.catid = c.id AND a.state = 1 AND (a.language = ' . $db->quote($languageCode) . ' OR a.language = ' . $db->quote('*') . ')')
					->where('c.published = 1')
					->where('c.extension = ' . $db->quote('com_content'))
					->where('(c.id = 137 OR c.parent_id = 137)')
					->where('(c.language = ' . $db->quote($languageCode) . ' OR c.language = ' . $db->quote('*') . ')')
					->group('c.id')
					->order('c.title ASC');

				// Apply limit and offset for categories list too
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
						$item->link = 'index.php?option=com_content&view=category&id=' . $item->slug;
					}
				}

				$result->success = 1;
				$result->data = $items;
			}
		} catch (Exception $e) {
			$result->success = 0;
			$result->data = [];
			$result->message = 'Error fetching IJ category data: ' . $e->getMessage();
		}

		return $result;
	}
	
	public function post()
	{  
		$this->plugin->setResponse("Use GET method");
	}
}
