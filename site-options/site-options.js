(function ($) {
  'use strict';

  function getPage() {
    const params = new URLSearchParams(window.location.search);
    return params.get('page') || '';
  }

  function slugify(str) {
    return String(str || '')
      .trim()
      .toLowerCase()
      .replace(/\s+/g, '-')
      .replace(/[^\u0590-\u05FFa-z0-9\-_]/gi, '')
      .replace(/-+/g, '-');
  }

  function getGroupTitle($group, index) {
    // רק הכותרת האמיתית של הקבוצה
    const $title = $group.find('> .postbox-header > h2.hndle').first();
    const text = $.trim($title.text());

    return text || ('קבוצה ' + (index + 1));
  }

  function getTopLevelTabs($group) {
    const tabs = [];

    // אצלך ה-tab-wrap יושב ישירות בתוך .inside.acf-fields
    const $tabLinks = $group.find('> .inside > .acf-tab-wrap:first > ul.acf-tab-group > li > a.acf-tab-button');

    $tabLinks.each(function (i) {
      const $a = $(this);
      const text = $.trim($a.text());

      if (!text) return;

      tabs.push({
        index: i,
        text: text,
        $a: $a
      });
    });

    return tabs;
  }

  function activateRealTab($group, tabIndex) {
    const $tabs = $group.find('> .inside > .acf-tab-wrap:first > ul.acf-tab-group > li > a.acf-tab-button');

    if (!$tabs.length) return;

    const $target = $tabs.eq(tabIndex);
    if ($target.length) {
      $target.trigger('click');
    }
  }

  function refreshAcf($group) {
    setTimeout(function () {
      $(window).trigger('resize');

      if (window.acf && typeof window.acf.doAction === 'function') {
        window.acf.doAction('refresh');
        window.acf.doAction('append', $group);
        window.acf.doAction('show', $group);
      }
    }, 80);
  }

  function buildLayout($container, $groups) {
    const $layout = $('<div class="ed-site-options-layout"></div>');
    const $sidebar = $(
      '<aside class="ed-site-options-sidebar">' +
        '<div class="ed-site-options-sidebar__inner">' +
          '<nav class="ed-site-options-nav"></nav>' +
        '</div>' +
      '</aside>'
    );
    const $content = $('<div class="ed-site-options-content"></div>');

    $groups.each(function () {
      $content.append(this);
    });

    $layout.append($sidebar, $content);
    $container.append($layout);

    return {
      $layout,
      $sidebar,
      $content,
      $nav: $sidebar.find('.ed-site-options-nav')
    };
  }

  function showGroup(groupsData, groupId, tabIndex) {
    groupsData.forEach(function (groupData) {
      const isTarget = groupData.id === groupId;

      groupData.$group.toggleClass('is-visible', isTarget);
      groupData.$navGroup.toggleClass('is-active', isTarget);

      if (!isTarget) return;

      if (typeof tabIndex === 'number') {
        activateRealTab(groupData.$group, tabIndex);

        groupData.$subnav.find('a').removeClass('is-active');
        groupData.$subnav.find('[data-tab-index="' + tabIndex + '"]').addClass('is-active');
      } else {
        const $activeReal = groupData.$group.find('> .inside > .acf-tab-wrap:first > ul.acf-tab-group > li.active');
        const activeIndex = $activeReal.length ? $activeReal.index() : 0;

        groupData.$subnav.find('a').removeClass('is-active');
        groupData.$subnav.find('[data-tab-index="' + activeIndex + '"]').addClass('is-active');
      }

      refreshAcf(groupData.$group);
    });
  }

  function init() {
    if (getPage() !== 'site-settings') return;

    const $allGroups = $('.acf-postbox');
    if (!$allGroups.length) return;

    const $groups = $allGroups.filter(function () {
      return $(this).find('> .postbox-header > h2.hndle').length > 0;
    });

    if (!$groups.length) return;

    const $parent = $groups.first().parent();
    if (!$parent.length) return;

    const layout = buildLayout($parent, $groups);
    const groupsData = [];

    $groups.each(function (index) {
      const $group = $(this);
      const title = getGroupTitle($group, index);
      const groupId = 'ed-group-' + slugify(title + '-' + index);
      const tabs = getTopLevelTabs($group);

      $group.attr('data-ed-group-id', groupId);

      const $navGroup = $('<div class="ed-site-options-nav-group"></div>');
      const $mainBtn = $('<button type="button" class="ed-site-options-nav-main"></button>').text(title);
      const $subnav = $('<div class="ed-site-options-subnav"></div>');

      $navGroup.append($mainBtn, $subnav);
      layout.$nav.append($navGroup);

      if (!tabs.length) {
        $navGroup.addClass('no-tabs');
      }

      tabs.forEach(function (tab) {
        const $link = $('<a href="#"></a>')
          .text(tab.text)
          .attr('data-tab-index', tab.index);

        $link.on('click', function (e) {
          e.preventDefault();
          showGroup(groupsData, groupId, tab.index);
        });

        $subnav.append($link);
      });

      $mainBtn.on('click', function () {
        showGroup(groupsData, groupId, 0);
      });

      groupsData.push({
        id: groupId,
        title: title,
        $group: $group,
        tabs: tabs,
        $navGroup: $navGroup,
        $subnav: $subnav
      });
    });

    if (groupsData.length) {
      showGroup(groupsData, groupsData[0].id, 0);
    }

    // סנכרון אם לוחצים על טאב אמיתי בתוך הקבוצה
    $(document).on('click', '.ed-site-options-content .acf-tab-wrap > ul.acf-tab-group > li > a.acf-tab-button', function () {
      const $a = $(this);
      const $group = $a.closest('.acf-postbox');
      const groupId = $group.attr('data-ed-group-id');
      const tabIndex = $a.closest('li').index();

      groupsData.forEach(function (groupData) {
        const isTarget = groupData.id === groupId;

        groupData.$navGroup.toggleClass('is-active', isTarget);
        groupData.$group.toggleClass('is-visible', isTarget);

        if (isTarget) {
          groupData.$subnav.find('a').removeClass('is-active');
          groupData.$subnav.find('[data-tab-index="' + tabIndex + '"]').addClass('is-active');
        }
      });

      refreshAcf($group);
    });
  }

  $(init);

})(jQuery);