/**
 * SPDX-FileCopyrightText: 2026 YooMail contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const ALIGNMENT_STYLES = {
	'image-style-align-left': {
		clear: 'none',
		display: 'table',
		float: 'left',
		marginLeft: '0',
		marginRight: '1.5em',
	},
	'image-style-align-right': {
		clear: 'none',
		display: 'table',
		float: 'right',
		marginLeft: '1.5em',
		marginRight: '0',
	},
	'image-style-block-align-left': {
		clear: 'both',
		display: 'table',
		marginLeft: '0',
		marginRight: 'auto',
	},
	'image-style-align-center': {
		clear: 'both',
		display: 'table',
		marginLeft: 'auto',
		marginRight: 'auto',
	},
	'image-style-block-align-right': {
		clear: 'both',
		display: 'table',
		marginLeft: 'auto',
		marginRight: '0',
	},
}

function mergeStyle(element, styles) {
	for (const [property, value] of Object.entries(styles)) {
		element.style[property] = value
	}
}

/**
 * CKEditor image alignment is class based. Inline the critical alignment style
 * before saving/sending so the rendered mail does not depend on CKEditor CSS.
 *
 * @param {string} value HTML body from CKEditor
 * @return {string}
 */
export function normalizeImageAlignment(value) {
	if (!value || typeof DOMParser === 'undefined') {
		return value
	}

	const parser = new DOMParser()
	const document = parser.parseFromString(value, 'text/html')

	for (const figure of document.querySelectorAll('figure.image')) {
		const image = figure.querySelector('img')

		for (const [className, styles] of Object.entries(ALIGNMENT_STYLES)) {
			if (!figure.classList.contains(className)) {
				continue
			}

			mergeStyle(figure, {
				textAlign: 'center',
				...styles,
			})

			if (image) {
				mergeStyle(image, {
					display: 'block',
					height: 'auto',
					maxWidth: '100%',
				})
			}
		}
	}

	for (const image of document.body.querySelectorAll('img:not(figure img)')) {
		mergeStyle(image, {
			height: 'auto',
			maxWidth: '100%',
			verticalAlign: 'middle',
		})

		for (const [className, styles] of Object.entries(ALIGNMENT_STYLES)) {
			if (!image.classList.contains(className)) {
				continue
			}

			mergeStyle(image, styles)
		}
	}

	return document.body.innerHTML
}
