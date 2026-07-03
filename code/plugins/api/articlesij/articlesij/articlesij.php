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
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Router\Route;

/**
 * Articles IJ Resource (Plural - returns multiple articles)
 */
class ArticlesijApiResourceArticlesij extends ApiResource
{
	public function get()
	{
		$this->plugin->setResponse($this->getArticles());
	}
	
	public function getArticles()
	{
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$app = Factory::getApplication();
		
		// Get parameters
		$article_id = $app->input->get('id', 0, 'INT');
		$catid = $app->input->get('category_id', 0, 'INT');
		$ij = $app->input->get('ij', 0, 'INT');
		$featured = $app->input->get('featured', 0, 'INT');
		$created_by = $app->input->get('created_by', 0, 'INT');
		$search = $app->input->get('search', '', 'STRING');
		$limitstart = $app->input->get('limitstart', 0, 'INT');
		$offset = $app->input->get('offset', 0, 'int'); // Additional offset parameter
		$limitstart = $app->input->get('limitstart', $offset, 'int'); // Use offset as default for limitstart
		$listOrder = $app->input->get('listOrder', 'DESC', 'STRING');
		$date_filtering = $app->input->get('date_filtering', '', 'STRING');
		$start_date = $app->input->get('start_date_range', '', 'STRING');
		$end_date = $app->input->get('end_date_range', '', 'STRING');
		$lang = $app->input->get('lang', 'pt', 'STRING');
		
		// Handle limit with nolimit support
		$nolimit = $app->input->get('nolimit', 0, 'INT');
		if ($nolimit == 1) {
			$limit = 0; // No limit
		} else {
			$limit = $app->input->get('limit', 0, 'INT'); // Default: no limit
		}
		
		try {
			// Get language code for filtering
			$languageCode = $lang === 'en' ? 'en-GB' : 'pt-BR';
			
			// Build query for IJ articles (category 137 and subcategories)
			$query = $db->getQuery(true)
				->select('a.id, a.title, a.alias, a.introtext, a.fulltext, a.created, a.created_by')
				->select('a.catid, a.state, a.access, a.featured, a.language, a.hits, a.images')
				->select('a.publish_up, a.publish_down, a.modified')
				->select('c.title AS category_title, c.alias AS category_alias, c.path AS category_route')
				->select('u.name AS author')
				->from('#__content AS a')
				->join('LEFT', '#__categories AS c ON c.id = a.catid')
				->join('LEFT', '#__users AS u ON u.id = a.created_by')
				->where('a.state = 1')
				->where('c.published = 1')
				->where('a.publish_up <= NOW()')
				->where('(a.publish_down IS NULL OR a.publish_down >= NOW())')
				->where('(a.language = ' . $db->quote($languageCode) . ' OR a.language = ' . $db->quote('*') . ')');

			// IJ specific filter - category 137 and subcategories
			$subquery = $db->getQuery(true)
				->select('sub.id')
				->from('#__categories AS sub')
				->join('INNER', '#__categories AS this ON sub.lft > this.lft AND sub.rgt < this.rgt')
				->where('this.id = 137')
				->where('sub.level <= this.level + 1');
			
			$query->where('(a.catid = 137 OR a.catid IN (' . $subquery . '))');

			// Apply additional filters
			if ($article_id) {
				$query->where('a.id = ' . (int) $article_id);
			}

			if ($catid && $catid != 137) {
				$query->where('a.catid = ' . (int) $catid);
			}

			if ($featured) {
				$query->where('a.featured = ' . (int) $featured);
			}

			if ($created_by) {
				$query->where('a.created_by = ' . (int) $created_by);
			}

			if ($search) {
				$searchTerm = $db->quote('%' . $search . '%');
				$query->where('(a.title LIKE ' . $searchTerm . ' OR a.introtext LIKE ' . $searchTerm . ')');
			}

			// Date filtering
			if ($date_filtering === 'range' && $start_date && $end_date) {
				$query->where('a.created >= ' . $db->quote($start_date));
				$query->where('a.created <= ' . $db->quote($end_date));
			}

			// Order and limit
			$query->order('a.created ' . ($listOrder === 'ASC' ? 'ASC' : 'DESC'));
			
			// Apply limit and offset
			if ($limit > 0) {
				$db->setQuery($query, $limitstart, $limit);
			} else {
				$db->setQuery($query);
			}
			$rows = $db->loadObjectList();

			$data = [];
			$baseUrl = rtrim(Uri::base(), '/');

			if ($ij) {
				// Simplified data for IJ
				$data['contador'] = new stdClass();
				$data['contador']->count = count($rows);
				
				foreach ($rows as $key => $row) {
					$item = new stdClass();
					$item->id = $row->id;
					$item->title = $row->title;
					$item->introtext = $row->introtext;
					$item->category_title = $row->category_title;
					$item->created = $row->created;
					$item->publish_up = $row->publish_up;

					// Build link (simplified)
					$item->link = $baseUrl . '/index.php?option=com_content&view=article&id=' . $row->id . '&catid=' . $row->catid;

					if ($row->images) {
						$images = json_decode($row->images);
						if ($images) {
							foreach ($images as $imgKey => $value) {
								if ($value) {
									$images->$imgKey = Uri::base() . $value;
								}
							}
							$item->images = $images;
						}
					}

					$data[$key] = $item;
				}
			} else {
				// Full data
				foreach ($rows as $key => $row) {
					$item = new stdClass();
					$item->id = $row->id;
					$item->title = $row->title;
					$item->alias = $row->alias;
					$item->introtext = $row->introtext;
					$item->fulltext = $row->fulltext;
					$item->catid = ['catid' => $row->catid, 'title' => $row->category_title];
					$item->state = $row->state;
					$item->created = $row->created;
					$item->modified = $row->modified;
					$item->publish_up = $row->publish_up;
					$item->publish_down = $row->publish_down;
					$item->access = $row->access;
					$item->featured = $row->featured;
					$item->language = $row->language;
					$item->hits = $row->hits;

					if ($row->images) {
						$images = json_decode($row->images);
						if ($images) {
							foreach ($images as $imgKey => $value) {
								if ($value) {
									$images->$imgKey = Uri::base() . $value;
								}
							}
							$item->images = $images;
						}
					}

					if ($row->created_by) {
						$item->created_by = ['id' => $row->created_by, 'name' => $row->author];
					}

					// Add custom fields
					try {
						if (class_exists('FieldsHelper')) {
							$fields = FieldsHelper::getFields('com_content.article', ['id' => $row->id]);
							foreach ($fields as $field) {
								if ($field->name == 'imagem-do-cabecalho') {
									$item->imagem_do_cabecalho = $field->value;
								}
							}
						}
					} catch (Exception $e) {
						// Fields not available
					}

					// Add area de atuacao (if helper exists)
					try {
						if (class_exists('BlogappContentHelper')) {
							$helper = new BlogappContentHelper();
							$item->id_area_atuacao = $helper->getAreadeAtuacao($row->id);
						}
					} catch (Exception $e) {
						// Helper not available
					}

					$data[$key] = $item;
				}
			}

			$result = new stdClass();
			if (count($data) > 0) {
				if (!$ij) {
					$response = new stdClass();
					$response->results = $data;
					$response->total = count($rows);
					
					$result->success = true;
					$result->data = $response;
				} else {
					// For IJ, return data directly
					return $data;
				}
			} else {
				$result->success = false;
				$result->message = 'No IJ articles found';
			}

			return $result;

		} catch (Exception $e) {
			$result = new stdClass();
			$result->success = false;
			$result->message = 'Error fetching IJ articles: ' . $e->getMessage();
			return $result;
		}
	}
	
	public function post()
	{  
		$this->plugin->setResponse("Use GET method");
	}
}