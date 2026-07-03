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
use Joomla\CMS\Access\Access;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;
use Advogados\Component\Advogados\Site\Model\AdvogadosModel;

/**
 * Advogado API Resource (Single lawyer by ID or codigo)
 *
 * @since  1.0.0
 */
class AdvogadosApiResourceAdvogado extends ApiResource
{
    /**
     * Handle GET requests for a single lawyer
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
            $advId = $app->input->get('id', 0, 'INT');
            $advCodigo = $app->input->get('codigo', '', 'STRING');
            $advSigla = $app->input->get('sigla', '', 'STRING');
            $advNome = $app->input->get('nome', '', 'STRING');
            $lang = $app->input->get('lang', '', 'STRING');

            // Require either ID, codigo, sigla, or nome for single resource
            if (empty($advId) && empty($advCodigo) && empty($advSigla) && empty($advNome)) {
                throw new Exception('Either id, codigo, sigla, or nome parameter is required for single lawyer lookup', 400);
            }

            $helper = new AdvogadosApiHelper();

            // Use custom query like in advogados.php to ensure consistency
            $db = Factory::getDbo();
            $query = $db->getQuery(true);

            $query->select('DISTINCT a.*')
                ->from('#__advogados AS a')
                ->where('a.state = 1'); // Only published items

            // // Add access level filter
            // $user = Factory::getUser();
            // $viewLevels = Access::getAuthorisedViewLevels($user->id);
            // if (!empty($viewLevels)) {
            //     $query->where('a.access IN (' . implode(',', array_map('intval', $viewLevels)) . ')');
            // } else {
            //     $query->where('a.access = 1'); // Default to Public access if no user levels
            // }

            // Add language filter
            if (!empty($lang)) {
                if ($lang === 'pt') {
                    $query->where('a.linguagem = ' . $db->quote('pt-BR'));
                } elseif ($lang === 'en') {
                    $query->where('a.linguagem = ' . $db->quote('en-GB'));
                }
            }

            // Add ID, codigo, sigla, or nome filter
            if ($advId > 0) {
                $query->where('a.id = ' . (int) $advId);
            } elseif (!empty($advCodigo)) {
                $query->where('a.codigo = ' . $db->quote($advCodigo));
            } elseif (!empty($advSigla)) {
                $query->where('a.codigo = ' . $db->quote($advSigla));
            } elseif (!empty($advNome)) {
                // Search by name - exact match or partial match
                $query->where('(a.nome = ' . $db->quote($advNome) . ' OR a.nome LIKE ' . $db->quote('%' . $advNome . '%') . ')');
            }

            $query->order('a.nome ASC'); // Order by name for consistent results
            $query->setLimit(1); // Only one result for single resource

            $db->setQuery($query);
            $items = $db->loadObjectList();

            // Validate items before processing
            if (!is_array($items)) {
                $items = [];
            }

            // For single resource, return first item or empty if not found
            if (empty($items)) {
                $this->plugin->setResponse([
                    'error' => false,
                    'message' => 'Lawyer not found',
                    'data' => null
                ]);
                return;
            }

            $advs = $helper->trataItensAdvogados($items);

            // Return the first (and only) item for single resource
            $this->plugin->setResponse(!empty($advs) ? $advs[0] : null);
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