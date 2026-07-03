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
 * Articles IJ Resource (Singular - returns single article by ID)
 */
class ArticlesijApiResourceArticleij extends ApiResource
{
	public function get()
	{
		$this->plugin->setResponse($this->getArticle());
	}
	
	public function getArticle()
	{
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$app = Factory::getApplication();
		
		// Get article ID - required parameter
		$article_id = $app->input->get('id', 0, 'INT');
		
		if (!$article_id) {
			$result = new stdClass();
			$result->success = false;
			$result->message = 'Article ID is required';
			return $result;
		}
		
		$lang = $app->input->get('lang', 'pt', 'STRING');
		
		try {
			// Get language code for filtering
			$languageCode = $lang === 'en' ? 'en-GB' : 'pt-BR';
			
			// Build query for single IJ article (category 137 and subcategories)
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
				->where('(a.language = ' . $db->quote($languageCode) . ' OR a.language = ' . $db->quote('*') . ')')
				->where('a.id = ' . (int) $article_id);

			// IJ specific filter - category 137 and subcategories
			$subquery = $db->getQuery(true)
				->select('sub.id')
				->from('#__categories AS sub')
				->join('INNER', '#__categories AS this ON sub.lft > this.lft AND sub.rgt < this.rgt')
				->where('this.id = 137')
				->where('sub.level <= this.level + 1');
			
			$query->where('(a.catid = 137 OR a.catid IN (' . $subquery . '))');

			$db->setQuery($query);
			$row = $db->loadObject();

			if (!$row) {
				$result = new stdClass();
				$result->success = false;
				$result->message = 'IJ Article not found or not published';
				return $result;
			}

			$baseUrl = rtrim(Uri::base(), '/');

			// Build full article data
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

			$result = new stdClass();
			$result->success = true;
			$result->data = $item;

			return $result;

		} catch (Exception $e) {
			$result = new stdClass();
			$result->success = false;
			$result->message = 'Error fetching IJ article: ' . $e->getMessage();
			return $result;
		}
	}
	
	public function post()
	{  
		$this->plugin->setResponse("Use GET method");
	}
}
