{include:{$BACKEND_CORE_PATH}/layout/templates/head.tpl}
{include:{$BACKEND_CORE_PATH}/layout/templates/structure_start_module.tpl}

<div class="pageTitle">
	<h2>{$lblEvents|ucfirst}: {$msgEditArticle|sprintf:{$item.title}}</h2>
	<div class="buttonHolderRight">
		<a href="{$detailURL}/{$item.url}{option:item.revision_id}?revision={$item.revision_id}{/option:item.revision_id}" class="button icon iconZoom previewButton targetBlank">
			<span>{$lblView|ucfirst}</span>
		</a>
		<a href="{$var|geturl:'export_registrations'}&id={$item.id}" class="button icon iconExport targetBlank">
			<span>{$lblExport|ucfirst}</span>
		</a>
	</div>
</div>

{form:edit}
	{$txtTitle} {$txtTitleError}

	<div id="pageUrl">
		<div class="oneLiner">
			{option:detailURL}<p><span><a href="{$detailURL}/{$item.url}">{$detailURL}/<span id="generatedUrl">{$item.url}</span></a></span></p>{/option:detailURL}
			{option:!detailURL}<p class="infoMessage">{$errNoModuleLinked}</p>{/option:!detailURL}
		</div>
	</div>

	<div class="tabs">
		<ul>
			<li><a href="#tabContent">{$lblContent|ucfirst}</a></li>
			<li><a href="#tabRevisions">{$lblPreviousVersions|ucfirst}</a></li>
			<li><a href="#tabPermissions">{$lblComments|ucfirst}</a></li>
			<li><a href="#tabSEO">{$lblSEO|ucfirst}</a></li>
		</ul>

		<div id="tabContent">
			<table border="0" cellspacing="0" cellpadding="0" width="100%">
				<tr>
					<td id="leftColumn">
						<div class="box">
							<div class="heading">
								<h3>{$lblDates|ucfirst}</h3>
							</div>
							<div class="options">
								<p class="p0"><label for="startsOnDate">{$lblStartsOn|ucfirst}<abbr title="{$lblRequiredField}">*</abbr></label></p>
								<div class="oneLiner">
									<p>
										{$txtStartsOnDate} {$txtStartsOnDateError}
									</p>
									<p>
										<label for="startsOnTime">{$lblAt}</label>
									</p>
									<p>
										{$txtStartsOnTime} {$txtStartsOnTimeError}
									</p>
								</div>
							</div>
							<div class="options">
								<p class="p0"><label for="endsOnDate">{$lblEndsOn|ucfirst}</label></p>
								<div class="oneLiner">
									<p>
										{$txtEndsOnDate} {$txtEndsOnDateError}
									</p>
									<p>
										<label for="endsOnTime">{$lblAt}</label>
									</p>
									<p>
										{$txtEndsOnTime} {$txtEndsOnTimeError}
									</p>
								</div>
							</div>
						</div>

						{* Main content *}
						<div class="box">
							<div class="heading">
								<h3>{$lblMainContent|ucfirst}<abbr title="{$lblRequiredField}">*</abbr></h3>
							</div>
							<div class="optionsRTE">
								{$txtText} {$txtTextError}
							</div>
						</div>

						<div class="box">
							<div class="heading">
								<h3>{$lblLocation|ucfirst}<abbr title="{$lblRequiredField}">*</abbr></h3>
							</div>
							<div class="options oneLiner">
								<p>
									{$txtLocation} {$txtLocationError}
								</p>
							</div>
						</div>

						<div class="box">
							<div class="heading">
								<h3>{$lblImage|ucfirst}</h3>
							</div>
							<div class="options">
								<label for="image">{$lblImage|ucfirst}</label>
								{option:item.image}<img src="{$item.image_url}" width="128" /><br />{/option:item.image}
								{$fileImage} {$fileImageError}
								<label for="deleteImage">{$chkRemoveImage} {$lblDelete|ucfirst}</label>
									{$chkRemoveImageError}
							</div>
						</div>

						{* Summary *}
						<div class="box">
							<div class="heading">
								<div class="oneLiner">
									<h3>{$lblSummary|ucfirst}</h3>
									<abbr class="help">(?)</abbr>
									<div class="tooltip" style="display: none;">
										<p>{$msgHelpSummary}</p>
									</div>
								</div>
							</div>
							<div class="optionsRTE">
								{$txtIntroduction} {$txtIntroductionError}
							</div>
						</div>

					</td>

					<td id="sidebar">
						<div id="publishOptions" class="box">
							<div class="heading">
								<h3>{$lblStatus|ucfirst}</h3>
							</div>

							{option:usingDraft}
							<div class="options">
								<div class="buttonHolder">
									<a href="{$detailURL}/{$item.url}?draft={$draftId}" class="button icon iconZoom targetBlank"><span>{$lblPreview|ucfirst}</span></a>
								</div>
							</div>
							{/option:usingDraft}

							<div class="options">
								<ul class="inputList">
									{iteration:hidden}
									<li>
										{$hidden.rbtHidden}
										<label for="{$hidden.id}">{$hidden.label}</label>
									</li>
									{/iteration:hidden}
								</ul>
							</div>

							<div class="options">
								<p class="p0"><label for="publishOnDate">{$lblPublishOn|ucfirst}</label></p>
								<div class="oneLiner">
									<p>
										{$txtPublishOnDate} {$txtPublishOnDateError}
									</p>
									<p>
										<label for="publishOnTime">{$lblAt}</label>
									</p>
									<p>
										{$txtPublishOnTime} {$txtPublishOnTimeError}
									</p>
								</div>
							</div>

							<div class="options">
								<ul class="inputList">
									<li>
										<label for="inThePicture">{$chkInThePicture} {$msgInThePicture}</label>
									</li>
								</ul>
							</div>
						</div>

						<div class="box" id="subscriptions">
							<div class="heading">
								<h3>{$lblSubscriptions|ucfirst}</h3>
							</div>
							<div class="options">
								<ul class="inputList">
									<li>
										<label for="subscriptionsOpen">
											{$chkAllowSubscriptions} {$lblEnabled|ucfirst} {$chkAllowSubscriptionsError}
										</label>
									</li>
								</ul>
								<p>
									<label for="maximumSubscriptions">{$lblMaximumSubscriptions|ucfirst}</label>
									{$txtMaxSubscriptions} {$txtMaxSubscriptionsError}
									<span class="helpTxt">{$msgMaxSubscriptions}</span>
								</p>
							</div>
						</div>

						<div class="box" id="articleMeta">
							<div class="heading">
								<h3>{$lblMetaData|ucfirst}</h3>
							</div>
							<div class="options">
								<label for="categoryId">{$lblCategory|ucfirst}</label>
								{$ddmCategoryId} {$ddmCategoryIdError}
							</div>
							<div class="options">
								<label for="userId">{$lblAuthor|ucfirst}</label>
								{$ddmUserId} {$ddmUserIdError}
							</div>
							<div class="options">
								<label for="tags">{$lblTags|ucfirst}</label>
								{$txtTags} {$txtTagsError}
							</div>
						</div>

					</td>
				</tr>
			</table>
		</div>

		<div id="tabPermissions">
			<table border="0" cellspacing="0" cellpadding="0" width="100%">
				<tr>
					<td>
						{$chkAllowComments} <label for="allowComments">{$lblAllowComments|ucfirst}</label>
					</td>
				</tr>
			</table>
		</div>

		<div id="tabRevisions">
			{option:drafts}
				<div class="tableHeading">
					<div class="oneLiner">
						<h3 class="oneLinerElement">{$lblDrafts|ucfirst}</h3>
						<abbr class="help">(?)</abbr>
						<div class="tooltip" style="display: none;">
							<p>{$msgHelpDrafts}</p>
						</div>
					</div>
				</div>
				<div class="dataGridHolder">
					{$drafts}
				</div>
			{/option:drafts}
			{option:revisions}
				<div class="tableHeading">
					<div class="oneLiner">
						<h3 class="oneLinerElement">{$lblPreviousVersions|ucfirst}</h3>
						<abbr class="help">(?)</abbr>
						<div class="tooltip" style="display: none;">
							<p>{$msgHelpRevisions}</p>
						</div>
					</div>
				</div>
				<div class="dataGridHolder">
					{$revisions}
				</div>
			{/option:revisions}
			{option:!revisions}{$msgNoRevisions}{/option:!revisions}
		</div>

		<div id="tabSEO">
			{include:{$BACKEND_CORE_PATH}/layout/templates/seo.tpl}
		</div>
	</div>

	<div class="fullwidthOptions">
		<a href="{$var|geturl:'delete'}&amp;id={$item.id}" data-message-id="confirmDelete" class="askConfirmation button linkButton icon iconDelete">
			<span>{$lblDelete|ucfirst}</span>
		</a>
		<div class="buttonHolderRight">
			<input id="editButton" class="inputButton button mainButton" type="submit" name="edit" value="{$lblPublish|ucfirst}" />
			<a href="#" id="saveAsDraft" class="inputButton button"><span>{$lblSaveDraft|ucfirst}</span></a>
		</div>
	</div>

	<div id="confirmDelete" title="{$lblDelete|ucfirst}?" style="display: none;">
		<p>
			{$msgConfirmDelete|sprintf:{$item.title}}
		</p>
	</div>
{/form:edit}

{include:{$BACKEND_CORE_PATH}/layout/templates/structure_end_module.tpl}
{include:{$BACKEND_CORE_PATH}/layout/templates/footer.tpl}