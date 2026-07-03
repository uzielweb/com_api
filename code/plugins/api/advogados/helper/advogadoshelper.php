<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  API.Advogados
 *
 * @copyright   Copyright (C) 2024 Machado Meyer. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

/**
 * Helper class for Advogados API
 *
 * @since  1.0.0
 */
class AdvogadosApiHelper
{
    /**
     * Component parameters
     *
     * @var    \Joomla\Registry\Registry
     * @since  1.0.0
     */
    protected $params;

    /**
     * Constructor
     *
     * @since   1.0.0
     */
    public function __construct()
    {
        $this->params = ComponentHelper::getParams('com_advogados');
    }

    /**
     * Process lawyer items for API response
     *
     * @param   array  $items  Array of lawyer items
     *
     * @return  array  Processed items
     *
     * @since   1.0.0
     */
    public function trataItensAdvogados($items): array
    {
        // Ensure we have a valid array
        if (!is_array($items)) {
            return [];
        }

        $fotopadraoadvogado = $this->params->get('fotopadraoadvogado');

        foreach ($items as $item) {
            // Ensure item is an object
            if (!is_object($item)) {
                continue;
            }

            try {
                // Remove unnecessary fields safely
                if (isset($item->editor)) unset($item->editor);
                if (isset($item->locais_escritorio_2060618)) unset($item->locais_escritorio_2060618);
                if (isset($item->tipos_nome_2060620)) unset($item->tipos_nome_2060620);
                if (isset($item->areadeatuaces_area_atuacao_2060639)) unset($item->areadeatuaces_area_atuacao_2060639);

                // Convert IDs to descriptive text
                $item->area_atuacao = $this->convertAreaAtuacaoIdsToText($item->area_atuacao ?? '');
                $item->area_atuacao_principal = $this->convertAreaAtuacaoIdsToText($item->area_atuacao_principal ?? '');
                $item->local = $this->convertLocalIdToText($item->local ?? 0);
                $item->tipo = $this->convertTipoIdToText($item->tipo ?? 0);
                $item->liderpratica = $this->convertLiderPraticaIdToText($item->liderpratica ?? 0);
                $item->created_by = $this->convertCreatedByIdToName($item->created_by ?? 0);
                $item->modified_by = $this->convertModifiedByIdToName($item->modified_by ?? 0);

                // Translate partner category safely
                if (isset($item->categoria_socio)) {
                    $item->categoria_socio = $this->convertCategoriaSocioToText($item->categoria_socio);
                }
            } catch (Exception $e) {
                // Log conversion error and continue
                error_log('Error converting advogado fields for ID ' . ($item->id ?? 'unknown') . ': ' . $e->getMessage());
            }
            
            // Set default photo if empty
            if (empty($item->foto)) {
                $item->foto = $fotopadraoadvogado;
            }
        }

        return $items;
    }

    /**
     * Sanitize text content
     *
     * @param   string  $text  Text to sanitize
     *
     * @return  string  Sanitized text
     *
     * @since   1.0.0
     */
    public function sanitize(string $text): string
    {
        $text = htmlspecialchars_decode($text);
        $text = str_ireplace('&nbsp;', ' ', $text);
        
        return $text;
    }

    /**
     * Convert area de atuacao IDs to text descriptions
     *
     * @param   string  $areaIds  Comma-separated area IDs
     *
     * @return  string  Area descriptions
     *
     * @since   1.0.0
     */
    public function convertAreaAtuacaoIdsToText(string $areaIds): string
    {
        if (empty($areaIds)) {
            return '';
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        
        // Split IDs and clean them
        $ids = array_filter(array_map('intval', explode(',', $areaIds)));
        
        if (empty($ids)) {
            return '';
        }

        $query->select('area_atuacao')
            ->from('#__advogados_area_atuacao')
            ->where('id IN (' . implode(',', $ids) . ')');

        // Check plugin parameter to exclude specific type_area values
        $excludedTypes = $this->params->get('excluded_type_areas', '3');
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
        $areas = $db->loadColumn();

        return is_array($areas) ? implode(' / ', $areas) : '';
    }

    /**
     * Convert local ID to text description
     *
     * @param   mixed  $localId  Local ID (string or int)
     *
     * @return  string  Local description
     *
     * @since   1.0.0
     */
    public function convertLocalIdToText($localId): string
    {
        $localIdInt = intval($localId);
        
        if ($localIdInt <= 0) {
            return '';
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        
        $query->select('escritorio')
            ->from('#__advogados_local')
            ->where('id = ' . (int) $localIdInt);

        $db->setQuery($query);
        $result = $db->loadResult();

        return $result ?: '';
    }

    /**
     * Convert tipo ID to text description
     *
     * @param   mixed  $tipoId  Tipo ID (string or int)
     *
     * @return  string  Tipo description
     *
     * @since   1.0.0
     */
    public function convertTipoIdToText($tipoId): string
    {
        $tipoIdInt = intval($tipoId);
        
        if ($tipoIdInt <= 0) {
            return '';
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        
        $query->select('nome')
            ->from('#__advogados_tipo')
            ->where('id = ' . (int) $tipoIdInt);

        $db->setQuery($query);
        $result = $db->loadResult();

        return $result ?: '';
    }

    /**
     * Convert lider pratica ID to text description
     *
     * @param   mixed  $liderId  Lider pratica ID (string or int, can be comma-separated)
     *
     * @return  string  Lider pratica description
     *
     * @since   1.0.0
     */
    public function convertLiderPraticaIdToText($liderId): string
    {
        if (empty($liderId)) {
            return '';
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        
        // Handle multiple IDs (comma-separated)
        $ids = array_filter(array_map('intval', explode(',', $liderId)));
        
        if (empty($ids)) {
            return '';
        }
        
        $query->select('nome')
            ->from('#__advogados')
            ->where('id IN (' . implode(',', $ids) . ')')
            ->where('state = 1')
            ->order('nome ASC');

        $db->setQuery($query);
        $results = $db->loadColumn();

        return is_array($results) ? implode(' / ', $results) : '';
    }

    /**
     * Convert created_by ID to user full name
     *
     * @param   mixed  $userId  User ID (string or int)
     *
     * @return  string  User full name
     *
     * @since   1.0.0
     */
    public function convertCreatedByIdToName($userId): string
    {
        $userIdInt = intval($userId);
        
        if ($userIdInt <= 0) {
            return '';
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        
        $query->select('name')
            ->from('#__users')
            ->where('id = ' . (int) $userIdInt);

        $db->setQuery($query);
        $result = $db->loadResult();

        return $result ?: '';
    }

    /**
     * Convert modified_by ID to user full name
     *
     * @param   mixed  $userId  User ID (string or int)
     *
     * @return  string  User full name
     *
     * @since   1.0.0
     */
    public function convertModifiedByIdToName($userId): string
    {
        $userIdInt = intval($userId);
        
        if ($userIdInt <= 0) {
            return '';
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        
        $query->select('name')
            ->from('#__users')
            ->where('id = ' . (int) $userIdInt);

        $db->setQuery($query);
        $result = $db->loadResult();

        return $result ?: '';
    }

    /**
     * Convert categoria socio to translated text
     *
     * @param   string  $categoria  Categoria socio value
     *
     * @return  string  Translated text
     *
     * @since   1.0.0
     */
    public function convertCategoriaSocioToText(string $categoria): string
    {
        if (empty($categoria)) {
            return '';
        }

        return Text::_('COM_ADVOGADOS_CADASTROS_CATEGORIA_SOCIO_OPTION_' . $categoria);
    }
}