<?php

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

/**
 * This is the overview-action
 *
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 */
class FrontendEventsIndex extends FrontendBaseBlock
{
	/**
	 * The articles
	 *
	 * @var	array
	 */
	private $items;

    /**
     * The used searchterm
     *
     * @var string
     */
    private $query = '';

    /**
     * The required month
     *
     * @var string
     */
    private $month;

    /**
     * The searchform
     *
     * @var FrontendForm
     */
    private $frm;

    /**
	 * The pagination array
	 * It will hold all needed parameters, some of them need initialization.
	 *
	 * @var	array
	 */
	protected $pagination = array('limit' => 10, 'offset' => 0, 'requested_page' => 1, 'num_items' => null, 'num_pages' => null);

	/**
	 * Execute the extra
	 */
	public function execute()
	{
		// call the parent
		parent::execute();

		// load template
		$this->loadTemplate();

        $this->loadForm();
        $this->validateForm();

		// load the data
		$this->getData();

        $this->frm->parse($this->tpl);

		// parse
		$this->parse();
	}

    /**
     * Load the form
     *
     * @return void
     */
    public function loadForm()
    {
        $this->frm = new FrontendForm('search');
        $this->frm->addText('query');
    }

    /**
	 * Load the data, don't forget to validate the incoming data
	 */
	private function getData()
	{
        if($this->query == '')
        {
            // requested page
            $requestedPage = $this->URL->getParameter('page', 'int', 1);

            // set URL and limit
            $this->pagination['url'] = FrontendNavigation::getURLForBlock('events');
            $this->pagination['limit'] = FrontendModel::getModuleSetting('events', 'overview_num_items', 10);

            // populate count fields in pagination
            $this->pagination['num_items'] = FrontendEventsModel::getAllCount();
            $this->pagination['num_pages'] = (int) ceil($this->pagination['num_items'] / $this->pagination['limit']);

            // num pages is always equal to at least 1
            if($this->pagination['num_pages'] == 0) $this->pagination['num_pages'] = 1;

            // redirect if the request page doesn't exist
            if($requestedPage > $this->pagination['num_pages'] || $requestedPage < 1) $this->redirect(FrontendNavigation::getURL(404));

            // populate calculated fields in pagination
            $this->pagination['requested_page'] = $requestedPage;
            $this->pagination['offset'] = ($this->pagination['requested_page'] * $this->pagination['limit']) - $this->pagination['limit'];

            // get articles
            $this->items = FrontendEventsModel::getAll($this->pagination['limit'], $this->pagination['offset']);


            // parse the pagination
            $this->parsePagination();
        }
        else
        {
            $this->items = FrontendEventsModel::searchEvents($this->query);
        }


	}

	/**
	 * Parse the data into the template
	 */
	private function parse()
	{
		// get RSS-link
		$rssLink = FrontendModel::getModuleSetting('events', 'feedburner_url_' . FRONTEND_LANGUAGE);
		if($rssLink == '') $rssLink = FrontendNavigation::getURLForBlock('events', 'rss');

		// add RSS-feed
		$this->header->addLink(array('rel' => 'alternate', 'type' => 'application/rss+xml', 'title' => FrontendModel::getModuleSetting('events', 'rss_title_' . FRONTEND_LANGUAGE), 'href' => $rssLink), true);

		// assign articles
		$this->tpl->assign('items', $this->items);

	}

    /**
     * Validate the form
     *
     * @return void
     */
    public function validateForm()
    {
        if($this->frm->isSubmitted())
        {
            if($this->frm->getField('query')->isFilled())
            {
                $this->query = $this->frm->getField('query')->getValue();
            }
        }
    }
}
