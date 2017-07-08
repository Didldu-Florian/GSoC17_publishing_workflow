<?php
/**
 * Items Model for a Prove Component.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_prove
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       4.0
 */
namespace Joomla\Component\Workflow\Administrator\Model;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Model\ListModel;

/**
 * Model class for items
 *
 * @since  4.0
 */
class Transitions extends ListModel
{
	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @see     JController
	 * @since   1.6
	 */
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id',
				'title',
				'from_state',
				'to_state'
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * This method should only be called once per instantiation and is designed
	 * to be called on the first call to the getState() method unless the model
	 * configuration flag to ignore the request is set.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 *
	 * @since   4.0
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$app = \JFactory::getApplication();
		$workflowID = $app->getUserStateFromRequest($this->context . '.filter.workflow_id', 'workflow_id', 1, 'cmd');
		$extension = $app->getUserStateFromRequest($this->context . '.filter.extension', 'extension', 'com_content', 'cmd');

		$this->setState('filter.workflow_id', $workflowID);
		$this->setState('filter.extension', $extension);

		parent::populateState($ordering, $direction);

		// TODO: Change the autogenerated stub
	}


	/**
	 * Method to get a table object, load it if necessary.
	 *
	 * @param   string  $type    The table name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  \Joomla\CMS\Table\Table  A JTable object
	 *
	 * @since   4.0
	 */
	public function getTable($type = 'Transition', $prefix = 'Administrator', $config = array())
	{
		return parent::getTable($type, $prefix, $config);
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  string  The query to database.
	 *
	 * @since   4.0
	 */
	public function getListQuery()
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		$select = $db->quoteName(array(
			'transition.id',
			'transition.title',
			'transition.published',
		));
		$select[] = $db->qn('f_state.title') . ' AS ' . $db->qn('from_state');
		$select[] = $db->qn('t_state.title') . ' AS ' . $db->qn('to_state');
		$joinTo = $db->qn('#__workflow_states') . ' AS ' . $db->qn('t_state') . ' ON ' . $db->qn('t_state.id') . ' = ' . $db->qn('transition.to_state_id');
		
		$query
			->select($select)
			->from($db->qn('#__workflow_transitions') . ' AS ' . $db->qn('transition'))
			->leftJoin($db->qn('#__workflow_states') . ' AS ' . $db->qn('f_state') . ' ON ' . $db->qn('f_state.id') . ' = ' . $db->qn('transition.from_state_id'))
			->leftJoin($joinTo);

		// Filter by extension
		if ($workflowID = (int) $this->getState('filter.workflow_id'))
		{
			$query->where($db->qn('transition.workflow_id') . ' = ' . $workflowID);
		}

		// Filter by condition
		if ($status = $this->getState('filter.published'))
		{
			$query->where($db->qn('published') . ' = ' . $db->quote($db->escape($status)));
		}

		// Filter by column from_state_id
		if ($fromState = $this->getState('filter.from_state'))
		{
			$query->where($db->qn('from_state_id') . ' = ' . $db->quote($db->escape($fromState)));
		}

		// Filter by column from_state_id
		if ($toState = $this->getState('filter.to_state'))
		{
			$query->where($db->qn('to_state_id') . ' = ' . $db->quote($db->escape($toState)));
		}

		// Filter by search in title
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			$search = $db->quote('%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%'));
			$query->where($db->qn('title') . ' LIKE ' . $search . ' OR ' . $db->qn('description') . ' LIKE ' . $search);
		}

		// Add the list ordering clause.
		$orderCol	= $this->state->get('list.ordering', 'id');
		$orderDirn 	= $this->state->get('list.direction', 'asc');

		$query->order($db->qn($db->escape($orderCol)) . ' ' . $db->escape($orderDirn));


		return $query;
	}
}
