<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_workflow
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Workflow\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Component\Workflow\Administrator\Table as WTable;

/**
 * Category table
 *
 * @since  1.6
 */
class State extends Table
{

	/**
	 * Constructor
	 *
	 * @param   \JDatabaseDriver  $db  Database connector object
	 *
	 * @since
	 */
	public function __construct(\JDatabaseDriver $db)
	{
		parent::__construct('#__workflow_states', 'id', $db);
		$this->access = (int) Factory::getConfig()->get('access');
	}

	/**
	 * Deletes workflow with transition and states.
	 *
	 * @param   int  $pk  Extension ids to delete.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   4.0
	 *
	 * @throws  \UnexpectedValueException
	 */
	public function delete($pk = null)
	{
		// @TODO: correct ACL check should be done in $model->canDelete(...) not here
		if (!\JFactory::getUser()->authorise('core.delete', 'com_workflows'))
		{
			throw new \Exception(\JText::_('JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED'), 403);
		}

		if (is_null($pk))
		{
			$pk = array();

			foreach ($this->_tbl_keys as $key)
			{
				$pk[$key] = $this->$key;
			}
		}
		elseif (!is_array($pk))
		{
			$pk = array($this->_tbl_key => $pk);
		}

		$db  = $this->getDbo();

		$state = new WTable\State($db);

		if (!$state->load($pk))
		{
			return false;
		}

		$db  = $this->getDbo();
		$app = \JFactory::getApplication();

		if ($state->default)
		{
			$app->enqueueMessage(\JText::sprintf('COM_WORKFLOW_MSG_DELETE_DEFAULT', $state->title), 'error');

			return false;
		}

		if ($state->published != -2)
		{
			// Delete only trashed => Error message

			return false;
		}

		// Delete the update site from all tables.
		try
		{
			// First delete the item then the transitions
			if (parent::delete($pk))
			{
				// Delete transitions
				$transition = new WTable\Transition($db);

				$query = $this->getDbo()->getQuery(true)
						->select($db->qn('id'))
						->from($db->qn('#__workflow_transitions'))
						->where($db->qn('to_state_id') . ' = ' . (int) $state->id, 'OR')
						->where($db->qn('from_state_id') . ' = ' . (int) $state->id);

				$transitions = $this->getDbo()->setQuery($query)->loadColumn();

				foreach ($transitions as $trans_id)
				{
					if ($transition->load($trans_id))
					{
						$transition->delete();
					}
				}

				return true;
			}

		}
		catch (\RuntimeException $e)
		{
			$app->enqueueMessage(\JText::sprintf('COM_WORKFLOW_MSG_WORKFLOWS_DELETE_ERROR', $state->title, $e->getMessage()), 'error');
		}

		return false;
	}

	/**
	 * Method to compute the default name of the asset.
	 * The default name is in the form table_name.id
	 * where id is the value of the primary key of the table.
	 *
	 * @return  string
	 *
	 * @since   1.6
	 */
	protected function _getAssetName()
	{
		$k = $this->_tbl_key;
		$workflow = new WTable\Workflow($this->getDbo());
		$workflow->load($this->workflow_id);

		return $workflow->extension . '.state.' . (int) $this->$k;
	}

	/**
	 * Method to return the title to use for the asset table.
	 *
	 * @return  string
	 *
	 * @since   1.6
	 */
	protected function _getAssetTitle()
	{
		return $this->title;
	}

	/**
	 * Get the parent asset id for the record
	 *
	 * @param   Table    $table  A JTable object for the asset parent.
	 * @param   integer  $id     The id for the asset
	 *
	 * @return  integer  The id of the asset's parent
	 *
	 * @since   1.6
	 */
	protected function _getAssetParentId(Table $table = null, $id = null)
	{
		$asset = self::getInstance('Asset', 'JTable', array('dbo' => $this->getDbo()));
		$workflow = new WTable\Workflow($this->getDbo());
		$workflow->load($this->workflow_id);
		$name = $workflow->extension . '.workflow.' . (int) $workflow->id;
		$asset->loadByName($name);
		$assetId = $asset->id;

		return !empty($assetId) ? $assetId : parent::_getAssetParentId($table, $id);
	}
}
