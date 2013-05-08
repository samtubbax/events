<?php

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

/**
 * In this file we store all generic functions that we will be using in the events module
 *
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 */
class BackendEventsModel
{
	const QRY_DATAGRID_BROWSE = 'SELECT i.id, i.revision_id, UNIX_TIMESTAMP(i.starts_on) AS starts_on, UNIX_TIMESTAMP(i.ends_on) AS ends_on, i.title, UNIX_TIMESTAMP(i.publish_on) AS publish_on, i.num_comments AS comments, i.num_subscriptions AS subscriptions
									FROM events AS i
									WHERE i.status = ? AND i.language = ?';
	const QRY_DATAGRID_BROWSE_CATEGORIES = 'SELECT i.id, i.title
											FROM events_categories AS i
											WHERE i.language = ?';
	const QRY_DATAGRID_BROWSE_COMMENTS = 'SELECT i.id, UNIX_TIMESTAMP(i.created_on) AS created_on, i.author, i.text,
											p.id AS event_id, p.title AS event_title, m.url AS event_url
											FROM events_comments AS i
											INNER JOIN events AS p ON i.event_id = p.id AND i.language = p.language
											INNER JOIN meta AS m ON p.meta_id = m.id
											WHERE i.status = ? AND i.language = ?
											GROUP BY i.id';
	const QRY_DATAGRID_BROWSE_DRAFTS = 'SELECT i.id, i.user_id, i.revision_id, i.title, UNIX_TIMESTAMP(i.edited_on) AS edited_on, i.num_comments AS comments
										FROM events AS i
										INNER JOIN
										(
											SELECT MAX(i.revision_id) AS revision_id
											FROM events AS i
											WHERE i.status = ? AND i.user_id = ? AND i.language = ?
											GROUP BY i.id
										) AS p
										WHERE i.revision_id = p.revision_id';
	const QRY_DATAGRID_BROWSE_RECENT = 'SELECT i.id, i.revision_id, i.title, UNIX_TIMESTAMP(i.edited_on) AS edited_on, i.user_id, i.num_comments AS comments
										FROM events AS i
										WHERE i.status = ? AND i.language = ?
										ORDER BY i.edited_on DESC
										LIMIT ?';
	const QRY_DATAGRID_BROWSE_REVISIONS = 'SELECT i.id, i.revision_id, i.title, UNIX_TIMESTAMP(i.edited_on) AS edited_on, i.user_id
											FROM events AS i
											WHERE i.status = ? AND i.id = ? AND i.language = ?
											ORDER BY i.edited_on DESC';
	const QRY_DATAGRID_BROWSE_SPECIFIC_DRAFTS = 'SELECT i.id, i.revision_id, i.title, UNIX_TIMESTAMP(i.edited_on) AS edited_on, i.user_id
													FROM events AS i
													WHERE i.status = ? AND i.id = ? AND i.language = ?
													ORDER BY i.edited_on DESC';
	const QRY_DATAGRID_BROWSE_SUBSCRIPTIONS = 'SELECT i.id, UNIX_TIMESTAMP(i.created_on) AS created_on, i.author,
												p.id AS event_id, p.title AS event_title, m.url AS event_url
												FROM events_subscriptions AS i
												INNER JOIN events AS p ON i.event_id = p.id AND i.language = p.language
												INNER JOIN meta AS m ON p.meta_id = m.id
												WHERE i.status = ? AND i.language = ?
												GROUP BY i.id';

	/**
	 * Checks the settings and optionally returns an array with warnings
	 *
	 * @return array
	 */
	public static function checkSettings()
	{
		// init var
		$warnings = array();

		// rss title
		if(BackendModel::getModuleSetting('events', 'rss_title_' . BL::getWorkingLanguage(), null) == '')
		{
			// add warning
			$warnings[] = array('message' => sprintf(BL::err('RSSTitle', 'events'), BackendModel::createURLForAction('settings', 'events')));
		}

		// rss description
		if(BackendModel::getModuleSetting('events', 'rss_description_' . BL::getWorkingLanguage(), null) == '')
		{
			// add warning
			$warnings[] = array('message' => sprintf(BL::err('RSSDescription', 'events'), BackendModel::createURLForAction('settings', 'events')));
		}

		// return
		return $warnings;
	}

	/**
	 * Deletes one or more items
	 *
	 * @param mixed $ids The ids to delete.
	 */
	public static function delete($ids)
	{
		// make sure $ids is an array
		$ids = (array) $ids;

		// get db
		$db = BackendModel::getContainer()->get('database');

		// delete records
		$db->delete('events', 'id IN (' . implode(',', $ids) . ') AND language = ?', array(BL::getWorkingLanguage()));
		$db->delete('events_comments', 'event_id IN (' . implode(',', $ids) . ') AND language = ?', array(BL::getWorkingLanguage()));

		// get used meta ids
		$metaIds = (array) $db->getColumn('SELECT meta_id
											FROM events AS p
											WHERE id IN (' . implode(',', $ids) . ') AND language = ?', array(BL::getWorkingLanguage()));

		// delete meta
		if(!empty($metaIds)) $db->delete('meta', 'id IN (' . implode(',', $metaIds) . ')');

		// invalidate the cache for events
		BackendModel::invalidateFrontendCache('events', BL::getWorkingLanguage());
	}

	/**
	 * Deletes a category
	 *
	 * @param int $id The id of the category to delete.
	 */
	public static function deleteCategory($id)
	{
		// redefine
		$id = (int) $id;

		// get db
		$db = BackendModel::getContainer()->get('database');

		// get item
		$item = self::getCategory($id);

		// any items?
		if(!empty($item))
		{
			// delete meta
			$db->delete('meta', 'id = ?', array($item['meta_id']));

			// delete category
			$db->delete('events_categories', 'id = ?', array($id));

			// default category
			$defaultCategoryId = BackendModel::getModuleSetting('events', 'default_category_' . BL::getWorkingLanguage(), null);

			// update category for the items that might be in this category
			$db->update('events', array('category_id' => $defaultCategoryId), 'category_id = ?', array($id));

			// invalidate the cache for events
			BackendModel::invalidateFrontendCache('events', BL::getWorkingLanguage());
		}
	}

	/**
	 * Deletes one or more comments
	 *
	 * @param array $ids The id(s) of the items(s) to delete.
	 */
	public static function deleteComments($ids)
	{
		// make sure $ids is an array
		$ids = (array) $ids;

		// get db
		$db = BackendModel::getContainer()->get('database');

		// get ids
		$itemIds = (array) $db->getColumn('SELECT i.event_id
											FROM events_comments AS i
											WHERE i.id IN (' . implode(',', $ids) . ') AND i.language = ?', array(BL::getWorkingLanguage()));

		// update record
		$db->delete('events_comments', 'id IN (' . implode(',', $ids) . ') AND language = ?', array(BL::getWorkingLanguage()));

		// recalculate the comment count
		if(!empty($itemIds)) self::reCalculateCommentCount($itemIds);

		// invalidate the cache for events
		BackendModel::invalidateFrontendCache('events', BL::getWorkingLanguage());
	}

	/**
	 * Delete all spam
	 */
	public static function deleteSpamComments()
	{
		// get db
		$db = BackendModel::getContainer()->get('database');

		// get ids
		$itemIds = (array) $db->getColumn('SELECT i.event_id
											FROM events_comments AS i
											WHERE status = ? AND i.language = ?', array('spam', BL::getWorkingLanguage()));

		// update record
		$db->delete('events_comments', 'status = ? AND language = ?', array('spam', BL::getWorkingLanguage()));

		// recalculate the comment count
		if(!empty($itemIds)) self::reCalculateCommentCount($itemIds);

		// invalidate the cache for events
		BackendModel::invalidateFrontendCache('events', BL::getWorkingLanguage());
	}

	/**
	 * Delete all spam
	 */
	public static function deleteSpamSubscriptions()
	{
		// get db
		$db = BackendModel::getContainer()->get('database');

		// get ids
		$itemIds = (array) $db->getColumn('SELECT i.event_id
											FROM events_subscriptions AS i
											WHERE status = ? AND i.language = ?', array('spam', BL::getWorkingLanguage()));

		// update record
		$db->delete('events_subscriptions', 'status = ? AND language = ?', array('spam', BL::getWorkingLanguage()));

		// recalculate the comment count
		if(!empty($itemIds)) self::reCalculateSubscriptionCount($itemIds);

		// invalidate the cache for events
		BackendModel::invalidateFrontendCache('events', BL::getWorkingLanguage());
	}

	/**
	 * Deletes one or more subscriptions
	 *
	 * @param array $ids The id(s) of the item(s) to delete.
	 */
	public static function deleteSubscriptions($ids)
	{
		// make sure $ids is an array
		$ids = (array) $ids;

		// get db
		$db = BackendModel::getContainer()->get('database');

		// get ids
		$itemIds = (array) $db->getColumn('SELECT i.event_id
											FROM events_subscriptions AS i
											WHERE i.id IN (' . implode(',', $ids) . ') AND i.language = ?', array(BL::getWorkingLanguage()));

		// update record
		$db->delete('events_subscriptions', 'id IN (' . implode(',', $ids) . ') AND language = ?', array(BL::getWorkingLanguage()));

		// recalculate the comment count
		if(!empty($itemIds)) self::reCalculateSubscriptionCount($itemIds);

		// invalidate the cache for events
		BackendModel::invalidateFrontendCache('events', BL::getWorkingLanguage());
	}

	/**
	 * Checks if an item exists
	 *
	 * @param int $id The id of the item to check for existence.
	 * @return bool
	 */
	public static function exists($id)
	{
		return (bool) BackendModel::getContainer()->get('database')->getVar('SELECT i.id
														FROM events AS i
														WHERE i.id = ? AND i.language = ?',
														array((int) $id, BL::getWorkingLanguage()));
	}

	/**
	 * Checks if a category exists
	 *
	 * @param int $id The id of the category to check for existence.
	 * @return int
	 */
	public static function existsCategory($id)
	{
		return (bool) BackendModel::getContainer()->get('database')->getVar('SELECT COUNT(id)
														FROM events_categories AS i
														WHERE i.id = ? AND i.language = ?',
														array((int) $id, BL::getWorkingLanguage()));
	}

	/**
	 * Checks if a comment exists
	 *
	 * @param int $id The id of the item to check for existence.
	 * @return int
	 */
	public static function existsComment($id)
	{
		return (bool) BackendModel::getContainer()->get('database')->getVar('SELECT COUNT(id)
														FROM events_comments AS i
														WHERE i.id = ? AND i.language = ?',
														array((int) $id, BL::getWorkingLanguage()));
	}

	/**
	 * Checks if a subscription exists
	 *
	 * @param int $id The id of the item to check for existence.
	 * @return int
	 */
	public static function existsSubscription($id)
	{
		return (bool) BackendModel::getContainer()->get('database')->getVar('SELECT COUNT(id)
														FROM events_subscriptions AS i
														WHERE i.id = ? AND i.language = ?',
														array((int) $id, BL::getWorkingLanguage()));
	}

	/**
	 * Get all data for a given id
	 *
	 * @param int $id The Id of the item to fetch?
	 * @return array
	 */
	public static function get($id)
	{
		$data = (array) BackendModel::getContainer()->get('database')->getRecord('SELECT i.*, UNIX_TIMESTAMP(i.starts_on) AS starts_on, UNIX_TIMESTAMP(i.ends_on) AS ends_on, UNIX_TIMESTAMP(i.publish_on) AS publish_on, UNIX_TIMESTAMP(i.created_on) AS created_on, UNIX_TIMESTAMP(i.edited_on) AS edited_on,
															m.url
															FROM events AS i
															INNER JOIN meta AS m ON m.id = i.meta_id
															WHERE i.id = ? AND i.status = ? AND i.language = ?',
															array((int) $id, 'active', BL::getWorkingLanguage()));

		if($data['image'] != null)
		{
			$data['image_url'] = FRONTEND_FILES_URL . '/events/source/' . $data['image'];
		}

		return $data;
	}

	/**
	 * Get the comments
	 *
	 * @param string[optional] $status The type of comments to get.
	 * @param int[optional] $limit The maximum number of items to retrieve.
	 * @param int[optional] $offset The offset.
	 * @return array
	 */
	public static function getAllCommentsForStatus($status, $limit = 30, $offset = 0)
	{
		// redefine
		if($status !== null) $status = (string) $status;
		$limit = (int) $limit;
		$offset = (int) $offset;

		// no status passed
		if($status === null)
		{
			// get data and return it
			return (array) BackendModel::getContainer()->get('database')->getRecords('SELECT i.id, UNIX_TIMESTAMP(i.created_on) AS created_on, i.author, i.email, i.website, i.text, i.type, i.status,
																p.id AS event_id, p.title AS event_title, m.url AS event_url, p.language AS event_language
																FROM events_comments AS i
																INNER JOIN events AS p ON i.event_id = p.id AND i.language = p.language
																INNER JOIN meta AS m ON p.meta_id = m.id
																WHERE i.language = ?
																GROUP BY i.id
																LIMIT ?, ?',
																array(BL::getWorkingLanguage(), $offset, $limit));
		}

		// get data and return it
		return (array) BackendModel::getContainer()->get('database')->getRecords('SELECT i.id, UNIX_TIMESTAMP(i.created_on) AS created_on, i.author, i.email, i.website, i.text, i.type, i.status,
															p.id AS event_id, p.title AS event_title, m.url AS event_url, p.language AS event_language
															FROM events_comments AS i
															INNER JOIN events AS p ON i.event_id = p.id AND i.language = p.language
															INNER JOIN meta AS m ON p.meta_id = m.id
															WHERE i.status = ? AND i.language = ?
															GROUP BY i.id
															LIMIT ?, ?',
															array($status, BL::getWorkingLanguage(), $offset, $limit));
	}

	/**
	 * Get all items by a given tag id
	 *
	 * @param int $tagId The id of the tag.
	 * @return array
	 */
	public static function getByTag($tagId)
	{
		// get the items
		$items = (array) BackendModel::getContainer()->get('database')->getRecords('SELECT i.id AS url, i.title AS name, mt.module
															FROM modules_tags AS mt
															INNER JOIN tags AS t ON mt.tag_id = t.id
															INNER JOIN events AS i ON mt.other_id = i.id
															WHERE mt.module = ? AND mt.tag_id = ? AND i.status = ? AND i.language = ?',
															array('events', (int) $tagId, 'active', BL::getWorkingLanguage()));

		// loop items and create url
		foreach($items as &$row) $row['url'] = BackendModel::createURLForAction('edit', 'events', null, array('id' => $row['url']));

		// return
		return $items;
	}

	/**
	 * Get all categories
	 *
	 * @return array
	 */
	public static function getCategories()
	{
		// get records and return them
		$categories = (array) BackendModel::getContainer()->get('database')->getPairs('SELECT i.id, i.title
																FROM events_categories AS i
																WHERE i.language = ?', array(BL::getWorkingLanguage()));

		// no categories?
		if(empty($categories))
		{
			// build array
			$category['language'] = BL::getWorkingLanguage();
			$category['title'] = 'default';

			// meta array
			$meta['keywords'] = 'default';
			$meta['keywords_overwrite'] = 'default';
			$meta['description'] = 'default';
			$meta['description_overwrite'] = 'default';
			$meta['title'] = 'default';
			$meta['title_overwrite'] = 'default';
			$meta['url'] = 'default';
			$meta['url_overwrite'] = 'default';
			$meta['custom'] = null;

			// insert meta
			$category['meta_id'] = $db->insert('meta', $category);

			// insert category
			$id = self::insertCategory($category);

			// store in settings
			BackendModel::setModuleSetting('events', 'default_category_' . BL::getWorkingLanguage(), $id);

			// recall
			return self::getCategories();
		}

		// return the categories
		return $categories;
	}

	/**
	 * Get all data for a given id
	 *
	 * @param int $id The id of the category to fetch.
	 * @return array
	 */
	public static function getCategory($id)
	{
		return (array) BackendModel::getContainer()->get('database')->getRecord('SELECT i.*
															FROM events_categories AS i
															WHERE i.id = ? AND i.language = ?',
															array((int) $id, BL::getWorkingLanguage()));
	}

	/**
	 * Get a category id by title
	 *
	 * @param string $title The title of the category.
	 * @param string[optional] $language The language to use, if not provided we will use the working language.
	 * @return int
	 */
	public static function getCategoryId($title, $language = null)
	{
		// redefine
		$title = (string) $title;
		$language = ($language !== null) ? (string) $language : BackendLanguage::getWorkingLanguage();

		// exists?
		return (int) BackendModel::getContainer()->get('database')->getVar('SELECT i.id
													FROM events_categories AS i
													WHERE i.title = ? AND i.language = ?',
													array($title, $language));
	}

	/**
	 * Get all data for a given id
	 *
	 * @param int $id The Id of the comment to fetch?
	 * @return array
	 */
	public static function getComment($id)
	{
		return (array) BackendModel::getContainer()->get('database')->getRecord('SELECT i.*, UNIX_TIMESTAMP(i.created_on) AS created_on,
															p.id AS event_id, p.title AS event_title, m.url AS event_url
															FROM events_comments AS i
															INNER JOIN events AS p ON i.event_id = p.id AND i.language = p.language
															INNER JOIN meta AS m ON p.meta_id = m.id
															WHERE i.id = ?
															LIMIT 1',
															array((int) $id));
	}

	/**
	 * Get multiple comments at once
	 *
	 * @param array $ids The id(s) of the comment(s).
	 * @return array
	 */
	public static function getComments(array $ids)
	{
		return (array) BackendModel::getContainer()->get('database')->getRecords('SELECT *
															FROM events_comments AS i
															WHERE i.id IN (' . implode(',', $ids) . ')');
	}

	/**
	 * Get a count per comment
	 *
	 * @return array
	 */
	public static function getCommentStatusCount()
	{
		return (array) BackendModel::getContainer()->get('database')->getPairs('SELECT i.status, COUNT(i.id)
															FROM events_comments AS i
															WHERE i.language = ?
															GROUP BY i.status',
															array(BL::getWorkingLanguage()));
	}

	/**
	 * Get the latest comments for a given type
	 *
	 * @param string $status The status for the comments to retrieve.
	 * @param int[optional] $limit The maximum number of items to retrieve.
	 * @return array
	 */
	public static function getLatestComments($status, $limit = 10)
	{
		// get the comments (order by id, this is faster then on date, the higher the id, the more recent
		$comments = (array) BackendModel::getContainer()->get('database')->getRecords('SELECT i.id, i.author, i.text, UNIX_TIMESTAMP(i.created_on) AS created_in,
																	p.title, p.language, m.url
																FROM events_comments AS i
																INNER JOIN events AS p ON i.event_id = p.id AND i.language = p.language
																INNER JOIN meta AS m ON p.meta_id = m.id
																WHERE i.status = ? AND p.status = ? AND i.language = ?
																ORDER BY i.id DESC
																LIMIT ?',
																array((string) $status, 'active', BL::getWorkingLanguage(), (int) $limit));

		// loop entries
		foreach($comments as $key => &$row)
		{
			// add full url
			$row['full_url'] = BackendModel::getURLForBlock('events', 'detail', $row['language']) . '/' . $row['url'];
		}

		// return
		return $comments;
	}

	/**
	 * Get the maximum id
	 *
	 * @return int
	 */
	public static function getMaximumId()
	{
		return (int) BackendModel::getContainer()->get('database')->getVar('SELECT MAX(id) FROM events LIMIT 1');
	}

	/**
	 * Get all data for a given revision
	 *
	 * @param int $id The id of the item.
	 * @param int $revisionId The revision to get.
	 * @return array
	 */
	public static function getRevision($id, $revisionId)
	{
		return (array) BackendModel::getContainer()->get('database')->getRecord('SELECT i.*, UNIX_TIMESTAMP(i.publish_on) AS publish_on, UNIX_TIMESTAMP(i.created_on) AS created_on, UNIX_TIMESTAMP(i.edited_on) AS edited_on, m.url
															FROM events AS i
															INNER JOIN meta AS m ON m.id = i.meta_id
															WHERE i.id = ? AND i.revision_id = ?',
															array((int) $id, (int) $revisionId));
	}

	/**
	 * Get all data for a given id
	 *
	 * @param int $id The id of the item to fetch?
	 * @return array
	 */
	public static function getSubscription($id)
	{
		return (array) BackendModel::getContainer()->get('database')->getRecord('SELECT i.*, UNIX_TIMESTAMP(i.created_on) AS created_on,
															p.id AS event_id, p.title AS event_title, m.url AS event_url
															FROM events_subscriptions AS i
															INNER JOIN events AS p ON i.event_id = p.id AND i.language = p.language
															INNER JOIN meta AS m ON p.meta_id = m.id
															WHERE i.id = ?
															LIMIT 1',
															array((int) $id));
	}

	/**
	 * Retrieve the unique URL for an item
	 *
	 * @param string $URL The URL to base on.
	 * @param int[optional] $id The id of the item to ignore.
	 * @return string
	 */
	public static function getURL($URL, $id = null)
	{
		// redefine URL
		$URL = SpoonFilter::urlise((string) $URL);

		// get db
		$db = BackendModel::getContainer()->get('database');

		// new item
		if($id === null)
		{
			// get number of categories with this URL
			$number = (int) $db->getVar('SELECT COUNT(i.id)
											FROM events AS i
											INNER JOIN meta AS m ON i.meta_id = m.id
											WHERE i.language = ? AND m.url = ?',
											array(BL::getWorkingLanguage(), $URL));

			// already exists
			if($number != 0)
			{
				// add number
				$URL = BackendModel::addNumber($URL);

				// try again
				return self::getURL($URL);
			}
		}

		// current category should be excluded
		else
		{
			// get number of items with this URL
			$number = (int) $db->getVar('SELECT COUNT(i.id)
											FROM events AS i
											INNER JOIN meta AS m ON i.meta_id = m.id
											WHERE i.language = ? AND m.url = ? AND i.id != ?',
											array(BL::getWorkingLanguage(), $URL, $id));

			// already exists
			if($number != 0)
			{
				// add number
				$URL = BackendModel::addNumber($URL);

				// try again
				return self::getURL($URL, $id);
			}
		}

		// return the unique URL!
		return $URL;
	}

	/**
	 * Retrieve the unique URL for a category
	 *
	 * @param string $URL The string wheron the URL will be based.
	 * @param int[optional] $id The id of the category to ignore.
	 * @return string
	 */
	public static function getURLForCategory($URL, $id = null)
	{
		// redefine URL
		$URL = SpoonFilter::urlise((string) $URL);

		// get db
		$db = BackendModel::getContainer()->get('database');

		// new category
		if($id === null)
		{
			// get number of categories with this URL
			$number = (int) $db->getVar('SELECT COUNT(i.id)
											FROM events_categories AS i
											INNER JOIN meta AS m ON i.meta_id = m.id
											WHERE i.language = ? AND m.url = ?',
											array(BL::getWorkingLanguage(), $URL));

			// already exists
			if($number != 0)
			{
				// add number
				$URL = BackendModel::addNumber($URL);

				// try again
				return self::getURLForCategory($URL);
			}
		}

		// current category should be excluded
		else
		{
			// get number of items with this URL
			$number = (int) $db->getVar('SELECT COUNT(i.id)
											FROM events_categories AS i
											INNER JOIN meta AS m ON i.meta_id = m.id
											WHERE i.language = ? AND m.url = ? AND i.id != ?',
											array(BL::getWorkingLanguage(), $URL, $id));

			// already exists
			if($number != 0)
			{
				// add number
				$URL = BackendModel::addNumber($URL);

				// try again
				return self::getURLForCategory($URL, $id);
			}
		}

		// return the unique URL!
		return $URL;
	}

	/**
	 * Inserts an item into the database
	 *
	 * @param array $item The data to insert.
	 * @return int
	 */
	public static function insert(array $item)
	{
		// insert and return the new revision id
		$item['revision_id'] = BackendModel::getContainer()->get('database')->insert('events', $item);

		// invalidate the cache for events
		BackendModel::invalidateFrontendCache('events', BL::getWorkingLanguage());

		// return the new revision id
		return $item['revision_id'];
	}

	/**
	 * Inserts a new category into the database
	 *
	 * @param array $item The data for the category to insert.
	 * @param array[optional] $meta The metadata for the category to insert.
	 * @return int
	 */
	public static function insertCategory(array $item, $meta = null)
	{
		// get db
		$db = BackendModel::getContainer()->get('database');

		// meta given?
		if($meta !== null) $item['meta_id'] = $db->insert('meta', $meta);

		// create category
		$item['id'] = $db->insert('events_categories', $item);

		// invalidate the cache for events
		BackendModel::invalidateFrontendCache('events', BL::getWorkingLanguage());

		// return the id
		return $item['id'];
	}

	/**
	 * Recalculate the commentcount
	 *
	 * @param array $ids The id(s) of the post wherefor the commentcount should be recalculated.
	 * @return bool
	 */
	public static function reCalculateCommentCount(array $ids)
	{
		// validate
		if(empty($ids)) return false;

		// make unique ids
		$ids = array_unique($ids);

		// get db
		$db = BackendModel::getContainer()->get('database');

		// get counts
		$commentCounts = (array) $db->getPairs('SELECT i.event_id, COUNT(i.id) AS comment_count
												FROM events_comments AS i
												INNER JOIN events AS p ON i.event_id = p.id AND i.language = p.language
												WHERE i.status = ? AND i.event_id IN (' . implode(',', $ids) . ') AND i.language = ? AND p.status = ?
												GROUP BY i.event_id',
												array('published', BL::getWorkingLanguage(), 'active'));

		// loop items
		foreach($ids as $id)
		{
			// get count
			$count = (isset($commentCounts[$id])) ? (int) $commentCounts[$id] : 0;

			// update
			$db->update('events', array('num_comments' => $count), 'id = ? AND language = ?', array($id, BL::getWorkingLanguage()));
		}

		return true;
	}

	/**
	 * Recalculate the subscriptioncount
	 *
	 * @param array $ids The id(s) of the post wherefor the subscriptioncount should be recalculated.
	 * @return bool
	 */
	public static function reCalculateSubscriptionCount(array $ids)
	{
		// validate
		if(empty($ids)) return false;

		// make unique ids
		$ids = array_unique($ids);

		// get db
		$db = BackendModel::getContainer()->get('database');

		// get counts
		$subscriptionsCounts = (array) $db->getPairs('SELECT i.event_id, COUNT(i.id) AS subscription_count
												FROM events_subscriptions AS i
												INNER JOIN events AS p ON i.event_id = p.id AND i.language = p.language
												WHERE i.status = ? AND i.event_id IN (' . implode(',', $ids) . ') AND i.language = ? AND p.status = ?
												GROUP BY i.event_id',
												array('published', BL::getWorkingLanguage(), 'active'));

		// loop items
		foreach($ids as $id)
		{
			// get count
			$count = (isset($subscriptionsCounts[$id])) ? (int) $subscriptionsCounts[$id] : 0;

			// update
			$db->update('events', array('num_subscriptions' => $count), 'id = ? AND language = ?', array($id, BL::getWorkingLanguage()));
		}

		return true;
	}

	/**
	 * Update an existing item
	 *
	 * @param array $item The new data.
	 * @return int
	 */
	public static function update(array $item)
	{
		// check if new version is active
		if($item['status'] == 'active')
		{
			// archive all older active versions
			BackendModel::getContainer()->get('database')->update('events', array('status' => 'archived'), 'id = ? AND status = ? AND language = ?', array($item['id'], $item['status'], BL::getWorkingLanguage()));

			// get the record of the exact item we're editing
			$revision = self::getRevision($item['id'], $item['revision_id']);

			// if it used to be a draft that we're now publishing, remove drafts
			if($revision['status'] == 'draft') BackendModel::getContainer()->get('database')->delete('events', 'id = ? AND status = ?', array($item['id'], $revision['status']));
		}

		// don't want revision id
		unset($item['revision_id']);

		// how many revisions should we keep
		$rowsToKeep = (int) BackendModel::getModuleSetting('events', 'max_num_revisions', 20);

		// set type of archive
		$archiveType = ($item['status'] == 'active' ? 'archived' : $item['status']);

		// get revision-ids for items to keep
		$revisionIdsToKeep = (array) BackendModel::getContainer()->get('database')->getColumn('SELECT i.revision_id
																		 FROM events AS i
																		 WHERE i.id = ? AND i.status = ? AND i.language = ?
																		 ORDER BY i.edited_on DESC
																		 LIMIT ?',
																		 array($item['id'], $archiveType, BL::getWorkingLanguage(), $rowsToKeep));

		// delete other revisions
		if(!empty($revisionIdsToKeep)) BackendModel::getContainer()->get('database')->delete('events', 'id = ? AND status = ? AND language = ? AND revision_id NOT IN (' . implode(', ', $revisionIdsToKeep) . ')', array($item['id'], BL::getWorkingLanguage(), $archiveType));

		// insert new version
		$item['revision_id'] = BackendModel::getContainer()->get('database')->insert('events', $item);

		// invalidate the cache for events
		BackendModel::invalidateFrontendCache('events', BL::getWorkingLanguage());

		// return the new revision id
		return $item['revision_id'];
	}

	/**
	 * Update an existing category
	 *
	 * @param array $item The new data.
	 * @param array[optional] $meta The new meta-data.
	 * @return int
	 */
	public static function updateCategory(array $item, $meta = null)
	{
		// get db
		$db = BackendModel::getContainer()->get('database');

		// update category
		$updated = $db->update('events_categories', $item, 'id = ?', array((int) $item['id']));

		// meta passed?
		if($meta !== null)
		{
			// get current category
			$category = self::getCategory($item['id']);

			// update the meta
			$db->update('meta', $meta, 'id = ?', array((int) $category['meta_id']));
		}

		// invalidate the cache for blog
		BackendModel::invalidateFrontendCache('events', BL::getWorkingLanguage());

		// return
		return $updated;
	}

	/**
	 * Update an existing comment
	 *
	 * @param array $item The new data.
	 * @return int
	 */
	public static function updateComment(array $item)
	{
		return BackendModel::getContainer()->get('database')->update('events_comments', $item, 'id = ?', array((int) $item['id']));
	}

	/**
	 * Updates one or more comments' status
	 *
	 * @param array $ids The id(s) of the comment(s) to change the status for.
	 * @param string $status The new status.
	 */
	public static function updateCommentStatuses($ids, $status)
	{
		// make sure $ids is an array
		$ids = (array) $ids;

		// get ids
		$itemIds = (array) BackendModel::getContainer()->get('database')->getColumn('SELECT i.event_id
																FROM events_comments AS i
																WHERE i.id IN (' . implode(',', $ids) . ')');

		// update record
		BackendModel::getContainer()->get('database')->execute('UPDATE events_comments
											SET status = ?
											WHERE id IN (' . implode(',', $ids) . ')',
											array((string) $status));

		// recalculate the comment count
		if(!empty($itemIds)) self::reCalculateCommentCount($itemIds);

		// invalidate the cache for events
		BackendModel::invalidateFrontendCache('events', BL::getWorkingLanguage());
	}

	/**
	 * Update a subscription
	 *
	 * @param array $item The new data.
	 * @return int
	 */
	public static function updateSubscription(array $item)
	{
		return BackendModel::getContainer()->get('database')->update('events_comments', $item, 'id = ?', array((int) $item['id']));
	}

	/**
	 * Updates one or more subscriptions' status
	 *
	 * @param array $ids The id(s) of the items(s) to change the status for.
	 * @param string $status The new status.
	 */
	public static function updateSubscriptionStatuses($ids, $status)
	{
		// make sure $ids is an array
		$ids = (array) $ids;

		// get ids
		$itemIds = (array) BackendModel::getContainer()->get('database')->getColumn('SELECT i.event_id
																FROM events_subscriptions AS i
																WHERE i.id IN (' . implode(',', $ids) . ')');

		// update record
		BackendModel::getContainer()->get('database')->execute('UPDATE events_subscriptions
											SET status = ?
											WHERE id IN (' . implode(',', $ids) . ')',
											array((string) $status));

		// recalculate the comment count
		if(!empty($itemIds)) self::reCalculateSubscriptionCount($itemIds);

		// invalidate the cache for events
		BackendModel::invalidateFrontendCache('events', BL::getWorkingLanguage());
	}
}
