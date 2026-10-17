/**
 * "Team Section" block: a lab's header, member roster or publications, for
 * the team chosen in the sidebar (or the team page the block is shown on).
 */
(function (wp) {
    'use strict';
    var el = wp.element.createElement;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var C = wp.components;
    var SSR = wp.serverSideRender.default || wp.serverSideRender;
    var data = window.rtmBlockData || { multiple: false, teams: [] };

    var sections = [
        { value: 'header', label: 'Team header (name, PI, intro, links)' },
        { value: 'roster', label: 'Team members' },
        { value: 'publications', label: 'Publications' }
    ];
    var teamOptions = [{ value: '0', label: 'The team of the page it is on' }].concat(data.teams.map(function (team) {
        return { value: String(team.id), label: team.lead ? team.name + ' — ' + team.lead : team.name };
    }));

    wp.blocks.registerBlockType('rtm/team-section', {
        apiVersion: 3,
        title: 'Team Section',
        description: 'A team’s header, member roster or publications. Choose the team (PI) in the sidebar.',
        category: 'rtm-blocks',
        icon: 'groups',
        attributes: {
            team: { type: 'number', default: 0 },
            section: { type: 'string', default: 'roster' },
            heading: { type: 'string', default: '' }
        },
        supports: { html: false, align: ['wide', 'full'] },
        edit: function (props) {
            var a = props.attributes;
            var section = sections.find(function (s) { return s.value === a.section; }) || sections[1];
            return el(wp.element.Fragment, null,
                el(InspectorControls, null,
                    el(C.PanelBody, { title: 'Team Section' },
                        el(C.SelectControl, { label: 'Team / PI', value: String(a.team), options: teamOptions, onChange: function (v) { props.setAttributes({ team: parseInt(v, 10) || 0 }); } }),
                        el(C.SelectControl, { label: 'Show', value: a.section, options: sections, onChange: function (v) { props.setAttributes({ section: v }); } }),
                        a.section !== 'header' ? el(C.TextControl, { label: 'Heading', value: a.heading, placeholder: a.section === 'roster' ? 'Lab Team' : 'Publications', onChange: function (v) { props.setAttributes({ heading: v }); } }) : null
                    )
                ),
                el('div', useBlockProps(),
                    a.team
                        ? el(SSR, { block: 'rtm/team-section', attributes: a })
                        : el(C.Placeholder, { icon: 'groups', label: 'Team Section: ' + section.label }, 'Shows the team of the page it is placed on (for example a team page template). Pick a specific team in the sidebar to preview it here.')
                )
            );
        },
        save: function () { return null; }
    });
})(window.wp);
