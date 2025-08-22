/**
 * Block Editor Extensions for Team Member Query Loops
 */

(function(wp) {
    const { addFilter } = wp.hooks;
    const { createHigherOrderComponent } = wp.compose;
    const { Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, SelectControl } = wp.components;

    // Add custom sort options to Query Loop blocks that show team members
    const withCustomQueryControls = createHigherOrderComponent((BlockEdit) => {
        return (props) => {
            const { attributes, setAttributes } = props;
            
            // Only add controls to Query Loop blocks
            if (props.name !== 'core/query') {
                return <BlockEdit {...props} />;
            }
            
            // Check if this query is for team members
            const { query } = attributes;
            if (!query || query.postType !== 'team_member') {
                return <BlockEdit {...props} />;
            }

            // Add our custom orderby options
            const customOrderByOptions = [
                { label: 'Default', value: 'date' },
                { label: 'Title', value: 'title' },
                { label: 'Date Joined (Start Date)', value: 'start_date' },
                { label: 'Date Left (End Date)', value: 'left_date' },
                { label: 'Date Priority + Order', value: 'date_priority' },
                { label: 'Custom Order', value: 'custom_order' },
                { label: 'Random', value: 'rand' },
            ];

            return (
                <Fragment>
                    <BlockEdit {...props} />
                    <InspectorControls>
                        <PanelBody title="Team Member Sorting" initialOpen={true}>
                            <SelectControl
                                label="Sort By"
                                value={query.orderBy || 'date'}
                                options={customOrderByOptions}
                                onChange={(value) => {
                                    setAttributes({
                                        query: {
                                            ...query,
                                            orderBy: value
                                        }
                                    });
                                }}
                                help="Choose how to sort team members. 'Date Priority + Order' will show prioritized members first, then sort by their custom order number, then by join date."
                            />
                        </PanelBody>
                    </InspectorControls>
                </Fragment>
            );
        };
    }, 'withCustomQueryControls');

    // Apply the filter
    addFilter(
        'editor.BlockEdit',
        'rtm/team-member-query-controls',
        withCustomQueryControls
    );

    // Modify the query arguments before they're sent to the REST API
    addFilter(
        'blocks.core/query.query',
        'rtm/modify-team-member-query',
        function(query, block) {
            // Only modify team_member queries
            if (query.postType !== 'team_member') {
                return query;
            }

            // Map our custom orderby values to the actual query parameters
            switch(query.orderBy) {
                case 'start_date':
                case 'joined_date':
                    return {
                        ...query,
                        orderBy: 'meta_value',
                        metaKey: '_rtm_start_date',
                        metaType: 'DATE'
                    };
                    
                case 'left_date':
                case 'end_date':
                    return {
                        ...query,
                        orderBy: 'meta_value',
                        metaKey: '_rtm_end_date',
                        metaType: 'DATE'
                    };
                    
                case 'date_priority':
                    // This is handled by the PHP pre_get_posts filter
                    return {
                        ...query,
                        orderBy: 'date_priority'
                    };
                    
                case 'custom_order':
                    return {
                        ...query,
                        orderBy: 'meta_value_num',
                        metaKey: '_rtm_order'
                    };
                    
                default:
                    return query;
            }
        }
    );

})(window.wp);