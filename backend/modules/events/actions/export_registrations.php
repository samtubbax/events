<?php

/**
 * This is the export subscriptions-action
 *
 * @author Sam Tubbax <sam@sumocoders.be>
 */
class BackendEventsExportRegistrations extends BackendBaseAction
{
	public function execute()
	{
		$id = SpoonFilter::getGetValue('id', null, null);
		// does the item exist
		if($id !== null)
		{
			$subscriptions = (array) BackendModel::getContainer()->get('database')->getRecords('SELECT author, email, created_on FROM events_subscriptions WHERE status="published" AND event_id = ?', $id);

			$filename = 'subscriptions_' . date('d-m-Y') . '_' . $id . '.csv';
			SpoonFileCSV::arrayToFile(FRONTEND_FILES_PATH . '/subscriptions/' . $filename, $subscriptions);
			$this->redirect(FRONTEND_FILES_URL . '/subscriptions/' . $filename);
		}
		else $this->redirect(BackendModel::createURLForAction('index') . '&error=non-existing');
	}
}