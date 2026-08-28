/**
 * SPDX-FileCopyrightText: 2026 YooMail contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const ALIGNMENT_STYLES = {
	'image-style-block-align-left': {
		marginLeft: '0',
		marginRight: 'auto',
	},
	'image-style-align-center': {
		marginLeft: 'auto',
		marginRight: 'auto',
	},
	'image-style-block-align-right': {
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
				clear: 'both',
				display: 'table',
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

	return document.body.innerHTML
}
