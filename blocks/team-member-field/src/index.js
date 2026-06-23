import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { groups as icon } from '@wordpress/icons';

const TEAM_MEMBER_FIELDS = [
	{ label: __('Position', 'research-team-manager'), value: 'rtm_position' },
	{ label: __('Short Description', 'research-team-manager'), value: 'rtm_short_description' },
	{ label: __('Long Description', 'research-team-manager'), value: 'rtm_long_description' },
	{ label: __('Email', 'research-team-manager'), value: 'rtm_email' },
	{ label: __('Phone Number', 'research-team-manager'), value: 'rtm_phonenumber' },
	{ label: __('Website', 'research-team-manager'), value: 'rtm_website' },
	{ label: __('LinkedIn URL', 'research-team-manager'), value: 'rtm_linkedin_url' },
	{ label: __('Google Scholar URL', 'research-team-manager'), value: 'rtm_google_scholar_url' },
	{ label: __('ResearchGate URL', 'research-team-manager'), value: 'rtm_researchgate_url' },
	{ label: __('Member Status', 'research-team-manager'), value: 'member_status' },
	{ label: __('Research Areas', 'research-team-manager'), value: 'research_areas' },
	{ label: __('Team Role', 'research-team-manager'), value: 'team_roles' },
	{ label: __('Team', 'research-team-manager'), value: 'research_team' }
];

registerBlockType('rtm/team-member-field', {
	edit: ({ attributes, setAttributes }) => {
		const {
			content,
			fieldName,
			showLabel,
			customLabel,
			fallbackText,
			makeLink,
			linkText
		} = attributes;

		const blockProps = useBlockProps({
			className: 'team-member-field-block'
		});

		const selectedField = TEAM_MEMBER_FIELDS.find(field => field.value === fieldName);
		const fieldLabel = customLabel || (selectedField ? selectedField.label : '');
		
		// Fields that can be links 
		const linkableFields = [
			'rtm_email', 'rtm_website', 'rtm_linkedin_url', 
			'rtm_google_scholar_url', 'rtm_researchgate_url'
		];
		
		const canBeLink = linkableFields.includes(fieldName);

		return (
			<div {...blockProps}>
				<InspectorControls>
					<PanelBody title={__('Field Settings', 'research-team-manager')}>
						<SelectControl
							label={__('Team Member Field', 'research-team-manager')}
							value={fieldName}
							options={TEAM_MEMBER_FIELDS}
							onChange={(value) => setAttributes({ fieldName: value })}
							help={__('Select which team member field to display', 'research-team-manager')}
							__next40pxDefaultSize={true}
							__nextHasNoMarginBottom={true}
						/>
						
						<ToggleControl
							label={__('Show Field Label', 'research-team-manager')}
							checked={showLabel}
							onChange={(value) => setAttributes({ showLabel: value })}
							help={__('Display a label before the field value', 'research-team-manager')}
							__nextHasNoMarginBottom={true}
						/>
						
						{showLabel && (
							<TextControl
								label={__('Custom Label', 'research-team-manager')}
								value={customLabel}
								onChange={(value) => setAttributes({ customLabel: value })}
								placeholder={selectedField ? selectedField.label : ''}
								help={__('Leave empty to use default label', 'research-team-manager')}
								__next40pxDefaultSize={true}
								__nextHasNoMarginBottom={true}
							/>
						)}
						
						<TextControl
							label={__('Fallback Text', 'research-team-manager')}
							value={fallbackText}
							onChange={(value) => setAttributes({ fallbackText: value })}
							placeholder={__('Not specified', 'research-team-manager')}
							help={__('Text to show when field is empty', 'research-team-manager')}
							__next40pxDefaultSize={true}
							__nextHasNoMarginBottom={true}
						/>
						
						{canBeLink && (
							<>
								<ToggleControl
									label={__('Make Link', 'research-team-manager')}
									checked={makeLink}
									onChange={(value) => setAttributes({ makeLink: value })}
									help={__('Turn field value into a clickable link', 'research-team-manager')}
									__nextHasNoMarginBottom={true}
								/>
								
								{makeLink && (
									<TextControl
										label={__('Link Text Override', 'research-team-manager')}
										value={linkText}
										onChange={(value) => setAttributes({ linkText: value })}
										placeholder={__('Leave empty to use field value', 'research-team-manager')}
										help={__('Custom text for the link (optional)', 'research-team-manager')}
										__next40pxDefaultSize={true}
										__nextHasNoMarginBottom={true}
									/>
								)}
							</>
						)}
					</PanelBody>
				</InspectorControls>

				<div className="team-member-field-preview">
					{content ? (
						<>
							{showLabel && fieldLabel && (
								<span className="team-member-field-label">
									{fieldLabel}:{' '}
								</span>
							)}
							<RichText
								tagName="span"
								className="team-member-field-content"
								value={content}
								onChange={(value) => setAttributes({ content: value })}
								placeholder={__('Add custom text...', 'research-team-manager')}
								allowedFormats={['core/bold', 'core/italic', 'core/link']}
							/>
						</>
					) : (
						<span className="team-member-field-placeholder">
							{showLabel && fieldLabel ? `${fieldLabel}: ` : ''}
							{makeLink && canBeLink ? '🔗 ' : ''}
							{__('[', 'research-team-manager')}{selectedField ? selectedField.label : fieldName}{__(']' , 'research-team-manager')}
							{makeLink && linkText ? ` (${linkText})` : ''}
						</span>
					)}
				</div>
			</div>
		);
	},

	save: ({ attributes }) => {
		const { content } = attributes;
		const blockProps = useBlockProps.save({
			className: 'team-member-field-block'
		});

		return (
			<div {...blockProps}>
				<RichText.Content
					tagName="span"
					className="team-member-field-content"
					value={content}
				/>
			</div>
		);
	}
});