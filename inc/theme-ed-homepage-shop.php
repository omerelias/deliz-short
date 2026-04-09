<?php
/**
 * Auto-split from functions-front.php — do not load directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//main slider
add_shortcode('ed_main_slider', function () {
  if ( ! have_rows('slider_settings', 'option') ) return '';

  // נחשב כמה באנרים כדי להחליט אם slick
  $rows = [];
  while ( have_rows('slider_settings', 'option') ) {
    the_row();
    $rows[] = [
      'desktop_image'     => get_sub_field('desktop_image'),
      'mobile_image'      => get_sub_field('mobile_image'),
      'text_on_image'     => (string) get_sub_field('text_on_image'),
      'btn_on_image'      => get_sub_field('btn_on_image'),
      'content_placement' => (string) get_sub_field('content_placement'),
    ];
  }

  $count = count($rows);
  if ( $count === 0 ) return '';

  ob_start(); ?>
  <div class="ed-main-slider <?php echo $count > 1 ? 'js-ed-main-slider' : 'is-static'; ?>">
    
    <?php $i = 0; foreach ($rows as $row):
        $i++;
      $placement = in_array($row['content_placement'], ['right','center','left'], true) ? $row['content_placement'] : 'center';

      $d = is_array($row['desktop_image']) ? $row['desktop_image'] : null;
      $m = is_array($row['mobile_image'])  ? $row['mobile_image']  : null;

      $d_id = isset($d['ID']) ? (int) $d['ID'] : 0;
      $m_id = isset($m['ID']) ? (int) $m['ID'] : 0;
      $desktop_url = $d['url'] ?? '';
      $mobile_url  = $m['url'] ?? '';
      if ( $d_id ) {
        $resized = wp_get_attachment_image_url($d_id, 'large');
        if ( $resized ) $desktop_url = $resized;
      }
      if ( $m_id ) {
        $resized = wp_get_attachment_image_url($m_id, 'medium_large');
        if ( $resized ) $mobile_url = $resized;
      }
      $alt = $d['alt'] ?? ($m['alt'] ?? '');
      $width  = isset($d['width']) ? (int) $d['width'] : '';
      $height = isset($d['height']) ? (int) $d['height'] : '';

      if ( ! $desktop_url ) continue;

      $text = trim($row['text_on_image']);

      $btn  = is_array($row['btn_on_image']) ? $row['btn_on_image'] : null;
      $btn_url    = $btn['url'] ?? '';
      $btn_title  = $btn['title'] ?? '';
      $btn_target = $btn['target'] ?? '';

      $is_first_slide = (int) $i === 1;
      echo '<div class="ed-slide ed-place--'.esc_attr($placement).'" data-ed-slide="'.(int)$i.'">';
      ?>
        <picture class="ed-slide__media">
          <?php if ($mobile_url): ?>
            <source media="(max-width: 767px)" srcset="<?php echo esc_url($mobile_url); ?>">
          <?php endif; ?>
          <img class="ed-slide__img" src="<?php echo esc_url($desktop_url); ?>" alt="<?php echo esc_attr($alt); ?>"<?php echo $width ? ' width="'.esc_attr($width).'"' : ''; ?><?php echo $height ? ' height="'.esc_attr($height).'"' : ''; ?> decoding="async"<?php echo $is_first_slide ? ' fetchpriority="high"' : ' loading="lazy"'; ?>>
        </picture>

        <?php if ($text || ($btn_url && $btn_title)): ?>
          <div class="ed-slide__content">
            <?php if ($text): ?>
              <div class="ed-slide__text"><?php echo esc_html($text); ?></div>
            <?php endif; ?>

            <?php if ($btn_url && $btn_title): ?>
              <a class="ed-slide__btn"
                 href="<?php echo esc_url($btn_url); ?>"
                 <?php echo $btn_target ? ' target="'.esc_attr($btn_target).'" rel="noopener noreferrer"' : ''; ?>>
                 <?php echo esc_html($btn_title); ?>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php
  return ob_get_clean();
});

// Preload תמונת הסליידר הראשונה (LCP) בדף הבית
add_action('wp_head', function () {
  if ( ! is_front_page() || ! function_exists('get_field') ) return;
  $slider = get_field('slider_settings', 'option');
  if ( empty($slider) || ! is_array($slider) ) return;
  $row = $slider[0];
  $d = isset($row['desktop_image']) && is_array($row['desktop_image']) ? $row['desktop_image'] : null;
  $m = isset($row['mobile_image']) && is_array($row['mobile_image']) ? $row['mobile_image'] : null;
  if ( ! $d || empty($d['url']) ) return;
  $d_id = isset($d['ID']) ? (int) $d['ID'] : 0;
  $m_id = $m && ! empty($m['ID']) ? (int) $m['ID'] : 0;
  $desktop_url = $d['url'];
  $mobile_url  = $m && ! empty($m['url']) ? $m['url'] : '';
  if ( $d_id ) {
    $resized = wp_get_attachment_image_url($d_id, 'large');
    if ( $resized ) $desktop_url = $resized;
  }
  if ( $m_id ) {
    $resized = wp_get_attachment_image_url($m_id, 'medium_large');
    if ( $resized ) $mobile_url = $resized;
  }
  if ( $mobile_url && $mobile_url !== $desktop_url ) {
    echo '<link rel="preload" as="image" href="' . esc_url($desktop_url) . '" imagesrcset="' . esc_url($mobile_url) . ' 768w, ' . esc_url($desktop_url) . ' 1200w" imagesizes="100vw">' . "\n";
  } else {
    echo '<link rel="preload" as="image" href="' . esc_url($desktop_url) . '">' . "\n";
  }
}, 1);

//menu shortcode
add_shortcode('ed_menu_sidebar', function ($atts) {
  $atts = shortcode_atts([
    'menu'  => 'תפריט קטגוריות',
    'class' => 'ed-mp-sidebar',
  ], $atts, 'ed_menu_sidebar');

  $menu_obj = wp_get_nav_menu_object($atts['menu']); 
  if (!$menu_obj) return '';

  $items = wp_get_nav_menu_items($menu_obj->term_id);
  if (empty($items)) return '';

  $cats = [];
  foreach ($items as $it) {
    if (($it->object ?? '') !== 'product_cat' || empty($it->object_id)) continue;
    $term = get_term((int)$it->object_id, 'product_cat');
    if (!$term || is_wp_error($term)) continue;

    $icon_field = function_exists('get_field') ? get_field('menu_item_icon', (int) $it->ID) : null;
    $icon_url   = '';
    if ( is_array($icon_field) && ! empty($icon_field['ID']) ) {
      $small = wp_get_attachment_image_url((int) $icon_field['ID'], 'ed_menu_icon');
      $icon_url = $small ?: ($icon_field['url'] ?? '');
    } elseif ( is_string($icon_field) ) {
      $icon_url = $icon_field;
    }
    $cats[] = [
      'title'    => $it->title,
      'slug'     => $term->slug,
      'icon_url' => $icon_url,
    ];
  }

  if (is_user_logged_in()) {
    array_unshift($cats, [
      'title'    => __( 'קנייה חוזרת', 'deliz-short' ),
      'slug'     => 'rebuy',
      'icon_url' => 'https://deliz-short.mywebsite.co.il/wp-content/uploads/2026/01/%D7%91%D7%A9%D7%A8-%D7%91%D7%A7%D7%A8.jpg', // אם תרצה אייקון קבוע תגיד לי
    ]);
  }

  if (!$cats) return '';

  // base URL = current page
  $page_url = get_permalink();
  $default_slug = $cats[0]['slug'];

  // enqueue shared JS once
  wp_register_script('ed-menu-products', false, [], null, true);
  wp_enqueue_script('ed-menu-products');

$config = [
  'endpoint'     => rest_url('ed/v1/products'),
  //'rebuyEndpoint' => rest_url('ed/v1/rebuy'),
  'rebuyViewEndpoint' => rest_url('ed/v1/rebuy-view'),
  'restNonce'     => wp_create_nonce('wp_rest'),
  'pageUrl'      => $page_url,
  'defaultSlug'  => $default_slug,
  'perPage'      => 10000,
  'productsSelector' => '[data-ed-products-box="1"]',
  'titleSelector'    => '[data-ed-products-title="1"]',
  'menuSelector'     => '[data-ed-term]',
  'catBase' => home_url('/cat/'),
  'searchEndpoint'       => rest_url('ed/v1/product-search'),
  'searchInputSelector'  => '#search_q',
  'searchWrapSelector'   => '.search',
  'cartFragmentsEndpoint' => rest_url('ed/v1/cart-fragments'),
];

  wp_add_inline_script('ed-menu-products', 'window.ED_MENU_PRODUCTS=' . wp_json_encode($config) . ';', 'before');
  wp_add_inline_script('ed-menu-products', ed_menu_products_js_shared(), 'after');

  ob_start(); ?>
    <div class="<?php echo esc_attr($atts['class']); ?>" data-ed-menu-sidebar="1">
      <ul class="ed-mp__menu">
        <?php
          $menu_icons_show = get_field('menu_icons_show', 'option');
          $menu_icons_round = get_field('menu_icons_round', 'option');
        ?>
        <?php foreach ($cats as $c):
          $href = home_url('/cat/' . $c['slug'] . '/');
          ?>
          <li class="ed-mp__item">
            <a class="ed-mp__link <?php if (!$menu_icons_show): ?>no-icon<?php endif; ?>"
               href="<?php echo esc_url($href); ?>"
               data-ed-term="<?php echo esc_attr($c['slug']); ?>">
              <?php if($menu_icons_show): ?>
                <?php if (!empty($c['icon_url'])): ?>                
                  <img class="ed-mp__icon icon-style-<?php echo $menu_icons_round; ?>" src="<?php echo esc_url($c['icon_url']); ?>" alt="" loading="lazy" />
                <?php endif; ?>
              <?php endif; ?>
              <span class="ed-mp__text"><?php echo esc_html($c['title']); ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php
  return ob_get_clean();
});

//items area
add_shortcode('ed_products_box', function ($atts) {
  $atts = shortcode_atts([
    'class'     => 'ed-mp-products-wrap',
    'title_tag' => 'h2',
    'per_page'  => 12,
    'columns'   => 2,
  ], $atts, 'ed_products_box');

  $tag = tag_escape($atts['title_tag']);

  $title_html    = '';
  $products_html = '';
  $subcats_html  = '';

  // אם נכנסו ישירות ל /cat/{slug}/ אז mp_cat קיים מה-rewrite
  $slug = get_query_var('mp_cat');

  if ($slug) {
    $term = get_term_by('slug', $slug, 'product_cat');

    if ($term && !is_wp_error($term)) {
      $title_html = esc_html($term->name);

      // מוצרים של הקטגוריה הנוכחית
      $products_html = do_shortcode(sprintf(
        '[products category="%s" limit="%d" paginate="false" columns="%d"]',
        esc_attr($slug),
        (int) $atts['per_page'],
        (int) $atts['columns']
      ));

      // תתי קטגוריות עם מוצרים בלבד
      $children = get_terms([
        'taxonomy'   => 'product_cat',
        'parent'     => (int) $term->term_id,
        'hide_empty' => true,
        'orderby'    => 'menu_order',
        'order'      => 'ASC',
      ]);

      if (!is_wp_error($children) && !empty($children)) {
        $items = [];

        foreach ($children as $child) {
          $url = trailingslashit(home_url('cat/' . $child->slug));

          $items[] = sprintf(
            '<a class="ed-mp__subcat-link" href="%s">%s</a>',
            esc_url($url),
            esc_html($child->name)
          );
        }

        if (!empty($items)) {
          $subcats_html = '<div class="ed-mp__subcats" data-ed-products-subcats="1">' . implode('', $items) . '</div>';
        }
      }
    }
  }

  return '
  <div class="' . esc_attr($atts['class']) . '">
    <' . $tag . ' class="ed-mp__title" data-ed-products-title="1">' . $title_html . '</' . $tag . '>
    ' . $subcats_html . '
    <div class="ed-mp__products" data-ed-products-box="1" aria-live="polite">' . $products_html . '</div>
  </div>';
});

function ed_menu_products_js_shared() {
  $ed_mp_i18n = wp_json_encode(
    array(
      'loadError'            => __( 'שגיאה בטעינה. נסה שוב.', 'deliz-short' ),
      'rebuyTitle'           => __( 'קנייה חוזרת', 'deliz-short' ),
      'contentUnavailable'   => __( 'תוכן לא זמין.', 'deliz-short' ),
      'rebuyLoadError'       => __( 'שגיאה בטעינת קנייה חוזרת.', 'deliz-short' ),
      'purchaseHistoryTitle' => __( 'הסטוריית הרכישה שלכם', 'deliz-short' ),
      'rebuyTabAll'          => __( 'מוצרים שקניתי', 'deliz-short' ),
      'rebuyTabLast'         => __( 'שחזור הזמנה קודמת', 'deliz-short' ),
      'searchAria'           => __( 'חפש', 'deliz-short' ),
      'clearSearchAria'      => __( 'נקה חיפוש', 'deliz-short' ),
      'searchResultsPrefix'  => __( 'תוצאות חיפוש עבור - ', 'deliz-short' ),
      'searchError'          => __( 'שגיאה בחיפוש. נסה שוב.', 'deliz-short' ),
    )
  );
  return <<<JS
(function () {
  const cfg = window.ED_MENU_PRODUCTS;
  const I18N = {$ed_mp_i18n};
  if (!cfg) return;

  const box   = document.querySelector(cfg.productsSelector);
  const title = document.querySelector(cfg.titleSelector);
  if (!box) return;

  if (!box.style.transition) {
    box.style.transition = 'opacity 0.22s ease, transform 0.22s ease';
  }
  if (!box.style.opacity) {
    box.style.opacity = '1';
  }
  if (!box.style.transform) {
    box.style.transform = 'translateY(0)';
  }

  const links = () => Array.from(document.querySelectorAll(cfg.menuSelector));
  let controller = null;
  let current = null;
  let lastTerm = null;
  let beforeSearch = null;

  function getSubcatsEl() {
    return document.querySelector('[data-ed-products-subcats="1"]');
  }

  function removeSubcats() {
    const el = getSubcatsEl();
    if (el) el.remove();
  }

  function replaceSubcats(html) {
    const currentEl = getSubcatsEl();

    if (html && String(html).trim()) {
      if (currentEl) {
        currentEl.outerHTML = html;
      } else if (title) {
        title.insertAdjacentHTML('afterend', html);
      }
    } else if (currentEl) {
      currentEl.remove();
    }
  }

  function fadeOutBox() {
    if (!box) return;
    box.style.opacity = '0';
    box.style.transform = 'translateY(10px)';
  }

  function fadeInBox() {
    if (!box) return;
    box.style.opacity = '0';
    box.style.transform = 'translateY(10px)';
    requestAnimationFrame(() => {
      box.style.opacity = '1';
      box.style.transform = 'translateY(0)';
    });
  }

  function getTermFromUrl() {
    const u = new URL(location.href);

    const p = u.pathname.replace(/\\/+$/, '');
    const parts = p.split('/').filter(Boolean);
    const i = parts.indexOf('cat');
    if (i !== -1 && parts[i + 1]) return parts[i + 1];

    return u.searchParams.get('mp_cat') || '';
  }

  function setActive(term) {
    links().forEach(a => a.classList.toggle('is-active', a.dataset.edTerm === term));
  }

  function setTitle(text) {
    if (!title) return;
    title.textContent = text || '';
  }

  function debounce(fn, wait = 300) {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), wait);
    };
  }

  async function loadTerm(term, {push=false} = {}) {
    if (term === 'rebuy') {
      await loadRebuyFromPhp({ push });
      return;
    }

    lastTerm = term;
    term = term || cfg.defaultSlug;
    if (!term || term === current) return;

    current = term;
    setActive(term);

    fadeOutBox();

    if (controller) controller.abort();
    controller = new AbortController();

    box.classList.add('is-loading');

    const link = links().find(a => a.dataset.edTerm === term);
    if (link) setTitle(link.textContent.trim());

    if (term === 'rebuy') {
      await loadRebuy({ push });
      return;
    }

    const url = new URL(cfg.endpoint);
    url.searchParams.set('term', term);
    url.searchParams.set('per_page', cfg.perPage);

    try {
      const res = await fetch(url.toString(), { signal: controller.signal, credentials: 'same-origin' });
      if (!res.ok) throw new Error('Request failed');
      const data = await res.json();

      box.innerHTML = data.html || '';
      box.classList.remove('is-loading');
      fadeInBox();

      if (data.term && data.term.name) setTitle(data.term.name);

      replaceSubcats(data.subcats_html || '');

      if (data.fragments && typeof data.fragments === 'object') {
        updateCartFragments(data.fragments);
      }

      if (push) {
        const base = (cfg.catBase || '/cat/').replace(/\\/+$/, '/');
        const newUrl = new URL(base + term + '/', window.location.origin);
        history.pushState({term}, '', newUrl.toString());
      }
    } catch (e) {
      if (e.name === 'AbortError') return;
      box.classList.remove('is-loading');
      box.innerHTML = '<p>' + I18N.loadError + '</p>';
      removeSubcats();
    }
  }

  async function loadRebuyFromPhp({push=false} = {}) {
    current = 'rebuy';
    setActive('rebuy');
    setTitle(I18N.rebuyTitle);
    removeSubcats();
    fadeOutBox();
    box.classList.add('is-loading');

    try {
      if (!cfg.rebuyViewEndpoint) {
        console.error('Missing cfg.rebuyViewEndpoint');
        throw new Error('missing endpoint');
      }

      const reqUrl = new URL(cfg.rebuyViewEndpoint);

      const res = await fetch(reqUrl.toString(), {
        credentials: 'same-origin',
        headers: {
          'X-WP-Nonce': cfg.restNonce
        }
      });

      const raw = await res.text();
      if (!res.ok) {
        console.error('rebuy-view failed', res.status, raw);
        throw new Error('rebuy-view failed: ' + res.status);
      }

      let data;
      try {
        data = JSON.parse(raw);
      } catch (e) {
        console.error('rebuy-view non-json response', raw);
        throw e;
      }

      box.innerHTML = (data && data.html) ? data.html : '<p>' + I18N.contentUnavailable + '</p>';
      box.classList.remove('is-loading');
      fadeInBox();

      replaceSubcats((data && data.subcats_html) ? data.subcats_html : '');

      if (push) {
        const base = (cfg.catBase || '/cat/').replace(/\/+$/, '/');
        const newUrl = new URL(base + 'rebuy' + '/', window.location.origin);
        history.pushState({term: 'rebuy'}, '', newUrl.toString());
      }
    } catch (e) {
      console.error('rebuy-view error', e);
      box.classList.remove('is-loading');
      box.innerHTML = '<p>' + I18N.rebuyLoadError + '</p>';
      removeSubcats();
    }
  }

  async function loadRebuy({push=false} = {}) {
    if (!cfg.rebuyEndpoint) return;

    current = 'rebuy';
    setActive('rebuy');
    setTitle(I18N.purchaseHistoryTitle);
    removeSubcats();

    fadeOutBox();
    box.classList.add('is-loading');

    const makeTabs = () => {
      return '<div class="ed-rb">' +
        '<div class="ed-rb__tabs" role="tablist">' +
        '<button class="ed-rb__tab is-active" type="button" data-rb-tab="all">' + I18N.rebuyTabAll + '</button>' +
        '<button class="ed-rb__tab" type="button" data-rb-tab="last">' + I18N.rebuyTabLast + '</button>' +
        '</div>' +
        '<div class="ed-rb__panel" data-rb-panel="1"></div>' +
        '</div>';
    };

    box.innerHTML = makeTabs();
    const panel = box.querySelector('[data-rb-panel="1"]');

    async function fetchMode(mode) {
      const url = new URL(cfg.rebuyEndpoint);
      url.searchParams.set('mode', mode);
      url.searchParams.set('per_page', cfg.perPage);

      const res = await fetch(url.toString(), {
        credentials: 'same-origin',
        headers: {
          'X-WP-Nonce': cfg.restNonce
        }
      });

      if (!res.ok) throw new Error('rebuy failed: ' + res.status);
      return await res.json();
    }

    try {
      const data = await fetchMode('all');
      panel.innerHTML = data.html || '';
      box.classList.remove('is-loading');
      fadeInBox();

      replaceSubcats(data.subcats_html || '');

      if (data.fragments && typeof data.fragments === 'object') {
        updateCartFragments(data.fragments);
      }

      if (push) {
        const base = (cfg.catBase || '/cat/').replace(/\/+$/, '/');
        history.pushState({term: 'rebuy'}, '', new URL(base + 'rebuy' + '/', window.location.origin).toString());
      }

      box.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-rb-tab]');
        if (!btn) return;

        const mode = btn.dataset.rbTab;
        box.querySelectorAll('.ed-rb__tab').forEach(b => b.classList.toggle('is-active', b === btn));

        panel.innerHTML = '';
        box.classList.add('is-loading');

        const d = await fetchMode(mode);
        panel.innerHTML = d.html || '';
        box.classList.remove('is-loading');

        replaceSubcats(d.subcats_html || '');

        if (d.fragments && typeof d.fragments === 'object') {
          updateCartFragments(d.fragments);
        }
      }, { once: true });

    } catch (e) {
      box.classList.remove('is-loading');
      box.innerHTML = '<p>' + I18N.rebuyLoadError + '</p>';
      removeSubcats();
    }
  }

  function getSearchWrap() {
    return document.querySelector(cfg.searchWrapSelector || '.search');
  }

  function getSearchInput() {
    return document.querySelector(cfg.searchInputSelector || '#search_q');
  }

  function ensureSearchUI() {
    const wrap = getSearchWrap();
    if (!wrap) return;

    if (!wrap.querySelector('.ed-search-btn')) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'ed-search-btn';
      b.setAttribute('aria-label', I18N.searchAria);
      b.innerHTML = '🔍';
      wrap.appendChild(b);
    }

    if (!wrap.querySelector('.ed-search-clear')) {
      const c = document.createElement('button');
      c.type = 'button';
      c.className = 'ed-search-clear';
      c.setAttribute('aria-label', I18N.clearSearchAria);
      c.style.display = 'none';
      c.innerHTML = '✕';
      wrap.appendChild(c);
    }
  }

  function showClear(show) {
    const wrap = getSearchWrap();
    if (!wrap) return;
    const s = wrap.querySelector('.ed-search-btn');
    const c = wrap.querySelector('.ed-search-clear');
    if (s) s.style.display = show ? 'none' : 'inline-flex';
    if (c) c.style.display = show ? 'inline-flex' : 'none';
  }

  async function loadSearch(q, {push=false} = {}) {
    const input = getSearchInput();
    const query = String(q || (input ? input.value : '') || '').trim();
    if (!query) return;

    q = (q || '').trim();
    if (!q) return;

    if (!beforeSearch) {
      beforeSearch = {
        html: box.innerHTML,
        title: title ? title.textContent : '',
        term: current || lastTerm || cfg.defaultSlug,
        url: location.href
      };
    }

    setActive('');
    setTitle(I18N.searchResultsPrefix + query);
    removeSubcats();
    showClear(true);

    if (controller) controller.abort();
    controller = new AbortController();

    fadeOutBox();
    box.classList.add('is-loading');

    const url = new URL(cfg.searchEndpoint);
    url.searchParams.set('q', query);
    url.searchParams.set('per_page', cfg.perPage);

    try {
      const res = await fetch(url.toString(), { signal: controller.signal, credentials: 'same-origin' });
      if (!res.ok) throw new Error('Search failed');
      const data = await res.json();

      box.innerHTML = data.html || '';
      box.classList.remove('is-loading');
      fadeInBox();

      replaceSubcats(data.subcats_html || '');

      if (data.fragments && typeof data.fragments === 'object') {
        updateCartFragments(data.fragments);
      }

    } catch (e) {
      if (e.name === 'AbortError') return;
      box.classList.remove('is-loading');
      box.innerHTML = '<p>' + I18N.searchError + '</p>';
      removeSubcats();
    }
  }

  function clearSearch() {
    const input = getSearchInput();
    if (input) input.value = '';

    if (beforeSearch) {
      box.innerHTML = beforeSearch.html || '';
      setTitle(beforeSearch.title || '');

      const backTerm = beforeSearch.term || cfg.defaultSlug;
      current = null;
      loadTerm(backTerm, {push: true});
      beforeSearch = null;
    } else {
      current = null;
      loadTerm(lastTerm || cfg.defaultSlug, {push: true});
    }

    showClear(false);
  }

  function bindSearch() {
    ensureSearchUI();

    const wrap = getSearchWrap();
    const input = getSearchInput();
    if (!wrap || !input) return;

    const btn = wrap.querySelector('.ed-search-btn');
    const clearBtn = wrap.querySelector('.ed-search-clear');

    const run = () => {
      const query = (input.value || '').trim();
      if (!query) return;
      loadSearch(query, {push: false});
    };

    const MIN_LEN = 2;

    const runLive = debounce(() => {
      const query = (input.value || '').trim();
      if (!query) {
        if (beforeSearch) clearSearch();
        return;
      }
      if (query.length < MIN_LEN) return;
      loadSearch(query, { push: false });
    }, 300);

    input.addEventListener('input', (e) => {
      e.stopPropagation();
      e.stopImmediatePropagation();
      runLive();
    }, true);

    btn && btn.addEventListener('click', run);
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); run(); }
      if (e.key === 'Escape') { e.preventDefault(); clearSearch(); }
    });

    clearBtn && clearBtn.addEventListener('click', clearSearch);
  }

  document.addEventListener('DOMContentLoaded', bindSearch);

  function updateCartFragments(fragments) {
    if (!fragments || typeof fragments !== 'object') return;

    Object.keys(fragments).forEach(selector => {
      const element = document.querySelector(selector);
      if (element && fragments[selector]) {
        element.outerHTML = fragments[selector];

        if (typeof jQuery !== 'undefined' && jQuery.fn.trigger) {
          jQuery(document.body).trigger('wc_fragment_refresh');
        }
      }
    });
  }

  async function refreshCartFragments() {
    if (!cfg.cartFragmentsEndpoint) return;

    try {
      const url = new URL(cfg.cartFragmentsEndpoint);
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      if (res.ok) {
        const data = await res.json();
        if (data.fragments) {
          updateCartFragments(data.fragments);
        }
      }
    } catch (e) {
      console.warn('Failed to refresh cart fragments:', e);
    }
  }

  window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
      refreshCartFragments();

      const term = getTermFromUrl();
      if (term) {
        current = null;
        loadTerm(term, {push: false});
      }
    }
  });

  document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
      refreshCartFragments();
    }
  });

  document.addEventListener('click', (e) => {
    const menuLink = e.target.closest(cfg.menuSelector);
    if (menuLink) {
      e.preventDefault();
      loadTerm(menuLink.dataset.edTerm, {push: true});
      return;
    }

    const subcatLink = e.target.closest('[data-ed-subcat]');
    if (subcatLink) {
      e.preventDefault();
      loadTerm(subcatLink.dataset.edSubcat, {push: true});
    }
  });

  window.addEventListener('popstate', () => {
    const term = getTermFromUrl();
    loadTerm(term);
    refreshCartFragments();
  });

  loadTerm(getTermFromUrl() || cfg.defaultSlug, {push:false});
})();
JS;
}
