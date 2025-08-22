/**
 * Extend Query Loop Block for Team Members
 */

(function() {
    // Wait for wp object to be available
    if (typeof wp === 'undefined' || !wp.hooks) {
        return;
    }

    // Add filter to modify the orderBy options for Query Loop blocks
    wp.hooks.addFilter(
        'blocks.registerBlockType',
        'rtm/modify-query-block',
        function(settings, name) {
            // Only modify the Query block
            if (name !== 'core/query') {
                return settings;
            }

            // Add our custom orderby options to the block's allowed values
            if (settings.attributes && settings.attributes.query) {
                const originalEnum = settings.attributes.query.default.orderBy || ['date', 'title'];
                
                // Add our custom options
                const customOptions = [
                    'start_date',
                    'joined_date', 
                    'left_date',
                    'end_date',
                    'date_priority',
                    'custom_order'
                ];
                
                // Merge options
                settings.attributes.query.default.orderBy = [...new Set([...originalEnum, ...customOptions])];
            }

            return settings;
        }
    );

    // Add filter to modify query arguments
    wp.hooks.addFilter(
        'editor.BlockListBlock',
        'rtm/modify-query-display',
        function(BlockListBlock) {
            return function(props) {
                // Check if this is a Query block for team members
                if (props.name === 'core/query' && 
                    props.attributes.query && 
                    props.attributes.query.postType === 'team_member') {
                    
                    // Log for debugging
                    console.log('Team Member Query Block detected', props.attributes.query);
                }
                
                return wp.element.createElement(BlockListBlock, props);
            };
        }
    );

})();