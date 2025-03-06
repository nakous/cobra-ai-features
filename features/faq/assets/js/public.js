// assets/js/faq.js

(function($) {
    'use strict';

    class CobraFAQ {
        constructor() {
            this.searchTimeout = null;
            this.pageNumber = 1;
            this.isLoading = false;

            this.init();
        }

        init() {
            this.initSearch();
            this.initToggle();
            this.initLoadMore();
            this.initHelpful();
            this.initShare();
        }

        initSearch() {
            const $search = $('.cobra-faq-search-input');
            const $categoryFilter = $('.cobra-faq-category-filter');
            const $results = $('.cobra-faq-search-results');

            if (!$search.length) return;

            $search.on('input', (e) => {
                const query = e.target.value.trim();
                const category = $categoryFilter.val();

                clearTimeout(this.searchTimeout);

                if (query.length < cobraFAQ.settings.min_chars) {
                    $results.hide();
                    return;
                }

                this.searchTimeout = setTimeout(() => {
                    this.performSearch(query, category);
                }, 300);
            });

            $categoryFilter.on('change', () => {
                const query = $search.val().trim();
                const category = $categoryFilter.val();

                if (query.length >= cobraFAQ.settings.min_chars) {
                    this.performSearch(query, category);
                }
            });
        }

        performSearch(query, category) {
            const $results = $('.cobra-faq-search-results');

            $.ajax({
                url: cobraFAQ.ajax_url,
                type: 'POST',
                data: {
                    action: 'cobra_faq_search',
                    nonce: cobraFAQ.nonce,
                    search: query,
                    category: category
                },
                beforeSend: () => {
                    $results.html('<div class="cobra-faq-loading">Searching...</div>').show();
                },
                success: (response) => {
                    if (response.success && response.data) {
                        let html = '';
                        if (response.data.length) {
                            response.data.forEach(item => {
                                html += `
                                    <div class="cobra-faq-result">
                                        <h4><a href="${item.url}">${item.title}</a></h4>
                                        <p>${item.excerpt}</p>
                                    </div>
                                `;
                            });
                        } else {
                            html = '<div class="cobra-faq-no-results">No results found</div>';
                        }
                        $results.html(html);
                    }
                },
                error: () => {
                    $results.html('<div class="cobra-faq-error">Error performing search</div>');
                }
            });
        }

        initToggle() {
            $(document).on('click', '.cobra-faq-toggle', (e) => {
                const $button = $(e.currentTarget);
                const $item = $button.closest('.cobra-faq-item');
                const $answer = $item.find('.cobra-faq-answer');
                const isExpanded = $button.attr('aria-expanded') === 'true';

                // Toggle aria-expanded
                $button.attr('aria-expanded', !isExpanded);

                // Toggle answer visibility with animation
                if (isExpanded) {
                    $answer.slideUp(200, () => {
                        $answer.attr('hidden', true);
                    });
                } else {
                    $answer.slideDown(200).removeAttr('hidden');
                    this.incrementViews($item.data('id'));
                }

                // Animate icon
                $button.find('.cobra-faq-icon').toggleClass('active');
            });
        }

        initLoadMore() {
            const $loadMore = $('.cobra-faq-load-more');
            if (!$loadMore.length) return;

            $loadMore.on('click', () => {
                if (this.isLoading) return;

                this.isLoading = true;
                this.pageNumber++;

                $.ajax({
                    url: cobraFAQ.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cobra_faq_load_more',
                        nonce: cobraFAQ.load_more_nonce,
                        page: this.pageNumber
                    },
                    beforeSend: () => {
                        $loadMore.addClass('loading');
                    },
                    success: (response) => {
                        if (response.success) {
                            $('.cobra-faq-list').append(response.data.html);
                            if (!response.data.hasMore) {
                                $loadMore.hide();
                            }
                        }
                    },
                    complete: () => {
                        this.isLoading = false;
                        $loadMore.removeClass('loading');
                    }
                });
            });
        }

        initHelpful() {
            $(document).on('click', '.cobra-faq-helpful-yes, .cobra-faq-helpful-no', (e) => {
                const $button = $(e.currentTarget);
                const faqId = $button.data('faq');
                const isHelpful = $button.hasClass('cobra-faq-helpful-yes');

                $.ajax({
                    url: cobraFAQ.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cobra_faq_helpful',
                        nonce: cobraFAQ.helpful_nonce,
                        faq_id: faqId,
                        helpful: isHelpful
                    },
                    success: () => {
                        const $helpfulSection = $button.closest('.cobra-faq-helpful');
                        $helpfulSection.html('<p>Thank you for your feedback!</p>');
                    }
                });
            });
        }

        initShare() {
            $(document).on('click', '.cobra-faq-share-button', (e) => {
                const $button = $(e.currentTarget);
                const url = $button.data('url');

                if (navigator.share) {
                    navigator.share({
                        url: url,
                    });
                } else {
                    // Fallback to copying to clipboard
                    const tempInput = document.createElement('input');
                    document.body.appendChild(tempInput);
                    tempInput.value = url;
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);

                    const $shareText = $button.find('.share-text');
                    const originalText = $shareText.text();
                    $shareText.text('Copied!');
                    setTimeout(() => {
                        $shareText.text(originalText);
                    }, 2000);
                }
            });
        }

        incrementViews(faqId) {
            $.ajax({
                url: cobraFAQ.ajax_url,
                type: 'POST',
                data: {
                    action: 'cobra_faq_increment_views',
                    nonce: cobraFAQ.views_nonce,
                    faq_id: faqId
                }
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new CobraFAQ();
    });

})(jQuery);