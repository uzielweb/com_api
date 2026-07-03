<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  API.Articles
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
 * Article API Resource (Single article by ID)
 *
 * @since  1.0.0
 */
class ArticlesApiResourceArticle extends ApiResource
{
	/**
	 * Handle GET requests for a single article
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @throws  Exception
	 */
	public function get(): void
	{
		try {
			$this->plugin->setResponse($this->getArticle());
		} catch (Exception $e) {
			$this->plugin->setResponse([
				'error' => true,
				'message' => $e->getMessage(),
				'code' => $e->getCode() ?: 500
			]);
		}
	}

	/**
	 * Get single article
	 *
	 * @return  object  Article data
	 *
	 * @since   1.0.0
	 */
	public function getArticle()
	{
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$app = Factory::getApplication();
		
		// Get parameters
		$article_id = $app->input->get('id', 0, 'INT');
		$alias = $app->input->get('alias', '', 'STRING');
		
		// Require either ID or alias for single resource
		if (empty($article_id) && empty($alias)) {
			throw new Exception('Either id or alias parameter is required for single article lookup', 400);
		}

		try {
			// Detect language from URL path
			$uri = Uri::getInstance();
			$path = $uri->getPath();
			$lang = 'pt'; // default
			if (strpos($path, '/en/') !== false) {
				$lang = 'en';
			}
			
			// Get language code for filtering
			$languageCode = $lang === 'en' ? 'en-GB' : 'pt-BR';
			
			// Build query for single article
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

			// Add ID or alias filter
			if ($article_id > 0) {
				$query->where('a.id = ' . (int) $article_id);
			} elseif (!empty($alias)) {
				$query->where('a.alias = ' . $db->quote($alias));
			}

			$query->setLimit(1); // Only one result for single resource

			$db->setQuery($query);
			$row = $db->loadObject();

			if (!$row) {
				$result = new stdClass();
				$result->success = false;
				$result->message = 'Article not found';
				$result->data = null;
				return $result;
			}

			// Process article data
			$baseUrl = Uri::base();
			
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

			// Process images
			if ($row->images) {
				$images = json_decode($row->images);
				if ($images) {
					foreach ($images as $imgKey => $value) {
						if ($value) {
							$images->$imgKey = $baseUrl . $value;
						}
					}
					$item->images = $images;
				}
			}

			// Build link
			$item->link = $baseUrl . 'index.php?option=com_content&view=article&id=' . $row->id . '&catid=' . $row->catid;

			// Add author info
			$item->created_by = ['id' => $row->created_by, 'name' => $row->author];

			// Try to get custom fields
			try {
				if (class_exists('Joomla\Component\Fields\Administrator\Helper\FieldsHelper')) {
					$fields = FieldsHelper::getFields('com_content.article', $row, true);
					if ($fields) {
						foreach ($fields as $field) {
							if ($field->name == 'imagem-do-cabecalho') {
								$item->imagem_do_cabecalho = $field->value;
							}
						}
					}
				}
			} catch (Exception $e) {
				// Fields not available
			}

			// Try to get area de atuacao (if helper exists)
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
			$result->message = 'Error fetching article: ' . $e->getMessage();
			return $result;
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
