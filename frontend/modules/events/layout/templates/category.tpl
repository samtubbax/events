{*
	variables that are available:
	- {$category}: contains data about the category
	- {$items}: contains an array with all items, each element contains data about the item
*}

{option:items}
	<section id="eventsIndex">
		{iteration:items}
			<article class="mod article">
				<div class="inner">
					<header class="hd">
						<h3>
							<a href="{$items.full_url}" title="{$items.title}">
								{$items.starts_on|date:{$dateFormatShort}:{$LANGUAGE}|ucfirst} -
								{$items.title}
							</a>
						</h3>
						<ul>
							<li>
								{* Written by *}
								{$msgWrittenBy|ucfirst|sprintf:{$items.user_id|usersetting:'nickname'}}

								{* Written on *}
								{$lblOn} {$items.publish_on|date:{$dateFormatLong}:{$LANGUAGE}}

								{* Category*}
								{$lblIn} {$lblThe} {$lblCategory} <a href="{$items.category_full_url}" title="{$items.category_title}">{$items.category_title}</a>{option:!items.tags}.{/option:!items.tags}

								{* Tags*}
								{option:items.tags}
									{$lblWith} {$lblThe} {$lblTags}
									{iteration:items.tags}
										<a href="{$items.tags.full_url}" rel="tag" title="{$items.tags.name}">{$items.tags.name}</a>{option:!items.tags.last}, {/option:!items.tags.last}{option:items.tags.last}.{/option:items.tags.last}
									{/iteration:items.tags}
								{/option:items.tags}
							</li>
							<li>
								{* Comments *}
								{option:!items.comments}<a href="{$items.full_url}#{$actComment}">{$msgEventsNoComments|ucfirst}</a>{/option:!items.comments}
								{option:items.comments}
									{option:items.comments_multiple}<a href="{$items.full_url}#{$actComments}">{$msgEventsNumberOfComments|sprintf:{$items.comments_count}}</a>{/option:items.comments_multiple}
									{option:!items.comments_multiple}<a href="{$items.full_url}#{$actComments}">{$msgEventsOneComment}</a>{/option:!items.comments_multiple}
								{/option:items.comments}
							</li>
						</ul>
					</header>
					<div class="bd content">
						{option:items.image}<img src="{$FRONTEND_FILES_URL}/events/images/source/{$items.image}" alt="{$items.title}" />{/option:items.image}
						{option:!items.introduction}{$items.text}{/option:!items.introduction}
						{option:items.introduction}{$items.introduction}{/option:items.introduction}
					</div>
				</div>
			</article>
		{/iteration:items}
	</section>
	{include:core/layout/templates/pagination.tpl}
{/option:items}
