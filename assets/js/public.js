/**
 * Research Team Manager - Public JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize team member filtering
        initTeamFilters();
        
        // Initialize publications display
        initPublications();
        
        // Initialize smooth scrolling for anchor links
        initSmoothScrolling();
        
        // Initialize lazy loading for images
        initLazyLoading();
        
    });

    /**
     * Initialize team member filtering functionality
     */
    function initTeamFilters() {
        // Filter toggle button
        $('.rtm-filter-toggle').on('click', function(e) {
            e.preventDefault();
            $(this).next('.rtm-filters-panel').slideToggle(300);
            $(this).toggleClass('active');
        });

        // Apply filters button
        $('.rtm-apply-filters').on('click', function(e) {
            e.preventDefault();
            applyTeamFilters();
            $('.rtm-filters-panel').slideUp(300);
            $('.rtm-filter-toggle').removeClass('active');
        });

        // Clear filters button
        $('.rtm-clear-filters').on('click', function(e) {
            e.preventDefault();
            clearTeamFilters();
        });

        // Filter on radio button change (real-time filtering)
        $('.rtm-filter-options input[type="radio"]').on('change', function() {
            if ($(this).closest('.rtm-filters-panel').hasClass('auto-filter')) {
                applyTeamFilters();
            }
        });

        // Close filters when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.rtm-filter-controls').length) {
                $('.rtm-filters-panel').slideUp(300);
                $('.rtm-filter-toggle').removeClass('active');
            }
        });
    }

    /**
     * Apply team member filters
     */
    function applyTeamFilters() {
        var selectedRole = $('input[name="role_filter"]:checked').val();
        var selectedArea = $('input[name="area_filter"]:checked').val();
        var visibleCount = 0;

        $('.rtm-team-member').each(function() {
            var $member = $(this);
            var memberRoles = ($member.data('roles') || '').toString().split(' ');
            var memberAreas = ($member.data('areas') || '').toString().split(' ');
            var showMember = true;

            // Filter by role
            if (selectedRole && selectedRole !== '' && memberRoles.indexOf(selectedRole) === -1) {
                showMember = false;
            }

            // Filter by research area
            if (selectedArea && selectedArea !== '' && memberAreas.indexOf(selectedArea) === -1) {
                showMember = false;
            }

            if (showMember) {
                $member.fadeIn(300);
                visibleCount++;
            } else {
                $member.fadeOut(300);
            }
        });

        // Show/hide no results message
        if (visibleCount === 0) {
            $('.rtm-no-results').fadeIn(300);
        } else {
            $('.rtm-no-results').fadeOut(300);
        }

        // Update URL with filters (optional)
        updateFilterURL(selectedRole, selectedArea);
    }

    /**
     * Clear all team member filters
     */
    function clearTeamFilters() {
        // Reset radio buttons to "All"
        $('input[name="role_filter"]').first().prop('checked', true);
        $('input[name="area_filter"]').first().prop('checked', true);
        
        // Show all team members
        $('.rtm-team-member').fadeIn(300);
        $('.rtm-no-results').fadeOut(300);
        
        // Close filters panel
        $('.rtm-filters-panel').slideUp(300);
        $('.rtm-filter-toggle').removeClass('active');
        
        // Clear URL parameters
        updateFilterURL('', '');
    }

    /**
     * Update URL with filter parameters
     */
    function updateFilterURL(role, area) {
        if (!window.history || !window.history.pushState) return;

        var url = new URL(window.location);
        
        if (role && role !== '') {
            url.searchParams.set('role', role);
        } else {
            url.searchParams.delete('role');
        }
        
        if (area && area !== '') {
            url.searchParams.set('area', area);
        } else {
            url.searchParams.delete('area');
        }
        
        window.history.pushState({}, '', url);
    }

    /**
     * Initialize publications functionality
     */
    function initPublications() {
        // Toggle abstract details
        $('.rtm-publication-abstract details').on('toggle', function() {
            if (this.open) {
                $(this).find('summary').addClass('expanded');
            } else {
                $(this).find('summary').removeClass('expanded');
            }
        });

        // Publications search/filter (if implemented)
        var searchTimeout;
        $('#rtm-publications-search').on('input', function() {
            clearTimeout(searchTimeout);
            var query = $(this).val().toLowerCase();
            
            searchTimeout = setTimeout(function() {
                filterPublications(query);
            }, 300);
        });
    }

    /**
     * Filter publications by search query
     */
    function filterPublications(query) {
        var visibleCount = 0;

        $('.rtm-publication-item').each(function() {
            var $item = $(this);
            var title = $item.find('.rtm-publication-title').text().toLowerCase();
            var authors = $item.find('.rtm-publication-authors').text().toLowerCase();
            var journal = $item.find('.rtm-publication-journal').text().toLowerCase();
            
            if (query === '' || 
                title.indexOf(query) !== -1 || 
                authors.indexOf(query) !== -1 || 
                journal.indexOf(query) !== -1) {
                $item.fadeIn(300);
                visibleCount++;
            } else {
                $item.fadeOut(300);
            }
        });

        // Show/hide no results message
        if (visibleCount === 0 && query !== '') {
            $('.rtm-publications-no-results').fadeIn(300);
        } else {
            $('.rtm-publications-no-results').fadeOut(300);
        }
    }

    /**
     * Initialize smooth scrolling for anchor links
     */
    function initSmoothScrolling() {
        $('a[href*="#"]').not('[href="#"]').not('[href="#0"]').on('click', function(e) {
            if (location.pathname.replace(/^\//, '') === this.pathname.replace(/^\//, '') && 
                location.hostname === this.hostname) {
                
                var target = $(this.hash);
                target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
                
                if (target.length) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: target.offset().top - 80
                    }, 800);
                }
            }
        });
    }

    /**
     * Initialize lazy loading for images
     */
    function initLazyLoading() {
        if ('IntersectionObserver' in window) {
            var imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('rtm-lazy');
                        img.classList.add('rtm-loaded');
                        imageObserver.unobserve(img);
                    }
                });
            });

            $('.rtm-lazy').each(function() {
                imageObserver.observe(this);
            });
        } else {
            // Fallback for browsers without IntersectionObserver
            $('.rtm-lazy').each(function() {
                var img = $(this);
                img.attr('src', img.data('src'));
                img.removeClass('rtm-lazy').addClass('rtm-loaded');
            });
        }
    }

    /**
     * Initialize social sharing functionality
     */
    function initSocialSharing() {
        $('.rtm-social-share').on('click', function(e) {
            e.preventDefault();
            
            var url = $(this).attr('href');
            var width = 600;
            var height = 400;
            var left = (screen.width / 2) - (width / 2);
            var top = (screen.height / 2) - (height / 2);
            
            window.open(url, 'share', 
                'width=' + width + 
                ',height=' + height + 
                ',left=' + left + 
                ',top=' + top + 
                ',toolbar=0,status=0,resizable=1,scrollbars=1'
            );
        });
    }

    /**
     * Initialize team member card interactions
     */
    function initTeamMemberCards() {
        // Hover effects for team member cards
        $('.rtm-team-member').on('mouseenter', function() {
            $(this).find('.rtm-member-actions').fadeIn(200);
        }).on('mouseleave', function() {
            $(this).find('.rtm-member-actions').fadeOut(200);
        });

        // Quick contact modal (if implemented)
        $('.rtm-quick-contact').on('click', function(e) {
            e.preventDefault();
            var memberName = $(this).data('member-name');
            var memberEmail = $(this).data('member-email');
            
            // Open quick contact modal
            openQuickContactModal(memberName, memberEmail);
        });
    }

    /**
     * Open quick contact modal
     */
    function openQuickContactModal(name, email) {
        // This would open a modal for quick contact
        // Implementation depends on your modal library/framework
        console.log('Opening quick contact for', name, email);
    }

    /**
     * Handle responsive navigation
     */
    function handleResponsiveNavigation() {
        var $toggleButton = $('.rtm-mobile-toggle');
        var $navigation = $('.rtm-team-navigation');

        $toggleButton.on('click', function() {
            $navigation.slideToggle(300);
            $(this).toggleClass('active');
        });

        // Close navigation when window is resized
        $(window).on('resize', function() {
            if ($(window).width() > 768) {
                $navigation.show();
                $toggleButton.removeClass('active');
            }
        });
    }

    /**
     * Initialize on page load
     */
    $(window).on('load', function() {
        // Apply filters from URL parameters on page load
        var urlParams = new URLSearchParams(window.location.search);
        var roleParam = urlParams.get('role');
        var areaParam = urlParams.get('area');

        if (roleParam) {
            $('input[name="role_filter"][value="' + roleParam + '"]').prop('checked', true);
        }

        if (areaParam) {
            $('input[name="area_filter"][value="' + areaParam + '"]').prop('checked', true);
        }

        if (roleParam || areaParam) {
            applyTeamFilters();
        }

        // Trigger any animations or effects after page load
        $('.rtm-team-member').addClass('loaded');
    });

    // Export functions for external use
    window.RTMPublic = {
        applyTeamFilters: applyTeamFilters,
        clearTeamFilters: clearTeamFilters,
        filterPublications: filterPublications
    };

})(jQuery);