/**
 * Block Editor Extensions for Team Member Query Loops
 */

(function(wp) {
    const { addFilter } = wp.hooks;
    const { createHigherOrderComponent } = wp.compose;
    const { Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, SelectControl, CheckboxControl } = wp.components;
    const data = window.rtmBlockData || { multiple: false, teams: [] };
    const TEAM_TAX = 'rtm_research_team';

    // Core Query Loop stores term filters in query.taxQuery: {taxonomy: [ids]}
    // before WordPress 7.0, {include: {taxonomy: [ids]}, exclude: {...}} since.
    function getTeamIds(query) {
        const tax = query.taxQuery || {};
        const ids = tax.include ? tax.include[TEAM_TAX] : tax[TEAM_TAX];
        return (ids || []).map(Number);
    }
    function withTeamIds(query, ids) {
        const tax = Object.assign({}, query.taxQuery || {});
        if (data.taxIncludeFormat || tax.include || tax.exclude) {
            tax.include = Object.assign({}, tax.include || {});
            if (ids.length) { tax.include[TEAM_TAX] = ids; } else { delete tax.include[TEAM_TAX]; }
        } else if (ids.length) {
            tax[TEAM_TAX] = ids;
        } else {
            delete tax[TEAM_TAX];
        }
        return Object.assign({}, query, { taxQuery: tax });
    }

    // "Teams / PIs" panel: no team ticked shows every team; tick one or more to filter.
    function teamPanel(query, setAttributes) {
        if (!data.multiple || !data.teams.length) {
            return null;
        }
        const selected = getTeamIds(query);
        return wp.element.createElement(PanelBody, {title: 'Teams / PIs', initialOpen: true},
            wp.element.createElement('p', {className: 'components-base-control__help'},
                selected.length ? 'Showing members of the ticked teams.' : 'Showing members of every team. Tick one or more teams to filter.'),
            data.teams.map(function (team) {
                return wp.element.createElement(CheckboxControl, {
                    key: team.id,
                    label: team.lead ? team.name + ' — ' + team.lead : team.name,
                    checked: selected.indexOf(team.id) !== -1,
                    onChange: function (on) {
                        const ids = on ? selected.concat([team.id]) : selected.filter(function (id) { return id !== team.id; });
                        setAttributes({query: withTeamIds(query, ids)});
                    }
                });
            })
        );
    }

    // Add custom sort options to Query Loop blocks that show team members
    const withCustomQueryControls = createHigherOrderComponent((BlockEdit) => {
        return (props) => {
            const { attributes, setAttributes } = props;
            
            // Only add controls to Query Loop blocks
            if (props.name !== 'core/query') {
                return wp.element.createElement(BlockEdit, props);
            }
            
            // Check if this query is for team members
            const { query } = attributes;
            if (!query || query.postType !== 'rtm_team_member') {
                return wp.element.createElement(BlockEdit, props);
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

            return wp.element.createElement(Fragment, null,
                wp.element.createElement(BlockEdit, props),
                wp.element.createElement(InspectorControls, null,
                    teamPanel(query, setAttributes),
                    wp.element.createElement(PanelBody, {title: 'Team Member Sorting', initialOpen: true},
                        wp.element.createElement(SelectControl, {
                            label: 'Sort By', value: query.orderBy || 'date', options: customOrderByOptions,
                            onChange: (value) => setAttributes({query: {...query, orderBy: value}}),
                            help: "Choose how to sort team members. 'Date Priority + Order' will show prioritized members first, then sort by their custom order number, then by join date."
                        })
                    )
                )
            );
        };
    }, 'withCustomQueryControls');

    // Apply the filter
    addFilter(
        'editor.BlockEdit',
        'rtm/team-member-query-controls',
        withCustomQueryControls
    );

})(window.wp);