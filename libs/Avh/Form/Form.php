<?php
if ( !defined('AVH_FRAMEWORK') )
	die('You are not allowed to call this page directly.');

/**
 * Form helper class.
 */
if ( !class_exists(AVH2_Form) ) {

	class AVH2_Form
	{

		/**
		 * Creates a form input.
		 * If no type is specified, a "text" type input will be returned.
		 *
		 * @param string $name
		 *        input name
		 * @param string $value
		 *        input value
		 * @param array $attributes
		 *        html attributes
		 * @return string
		 */
		protected static function _input ($name, $value = NULL, array $attributes = NULL)
		{
			// Set the input name
			$attributes['name'] = $name;

			// Set the input value
			$attributes['value'] = $value;

			if ( !isset($attributes['type']) ) {
				// Default type is text
				$attributes['type'] = 'text';
			}

			return '<input' . AVH2_Html::attributes($attributes) . ' />';
		}

		/**
		 * Creates a hidden form input.
		 *
		 * @param string $name
		 *        input name
		 * @param string $value
		 *        input value
		 * @param array $attributes
		 *        html attributes
		 * @return string
		 */
		protected static function _hidden ($name, $value = NULL, array $attributes = NULL)
		{
			$attributes['type'] = 'hidden';

			return AVH2_Form::input($name, $value, $attributes);
		}

		/**
		 * Creates a password form input.
		 *
		 * @param string $name input name
		 * @param string $value
		 *        input value
		 * @param array $attributes
		 *        html attributes
		 * @return string
		 */
		protected static function _password ($name, $value = NULL, array $attributes = NULL)
		{
			$attributes['type'] = 'password';

			return AVH2_Form::input($name, $value, $attributes);
		}

		/**
		 * Creates a file upload form input.
		 * No input value can be specified.
		 *
		 * @param string $name
		 *        input name
		 * @param array $attributes
		 *        html attributes
		 * @return string
		 */
		protected static function _file ($name, array $attributes = NULL)
		{
			$attributes['type'] = 'file';

			return AVH2_Form::input($name, NULL, $attributes);
		}

		/**
		 * Creates a checkbox form input.
		 *
		 * @param string $name
		 *        input name
		 * @param string $value
		 *        input value
		 * @param boolean $checked
		 *        checked status
		 * @param array $attributes
		 *        html attributes
		 * @return string
		 */
		protected static function _checkbox ($name, $value = NULL, $checked = FALSE, array $attributes = NULL)
		{
			$attributes['type'] = 'checkbox';

			if ( $checked === TRUE ) {
				// Make the checkbox active
				$attributes['checked'] = 'checked';
			}

			return AVH2_Form::input($name, $value, $attributes);
		}

		/**
		 * Creates a radio form input.
		 *
		 * @param string $name
		 *        input name
		 * @param string $value
		 *        input value
		 * @param boolean $checked
		 *        checked status
		 * @param array $attributes
		 *        html attributes
		 * @return string
		 */
		protected static function _radio ($name, $value = NULL, $checked = FALSE, array $attributes = NULL)
		{
			$attributes['type'] = 'radio';

			if ( $checked === TRUE ) {
				// Make the radio active
				$attributes['checked'] = 'checked';
			}

			return AVH2_Form::input($name, $value, $attributes);
		}

		/**
		 * Creates a textarea form input.
		 *
		 * @param string $name
		 *        textarea name
		 * @param string $body
		 *        textarea body
		 * @param array $attributes
		 *        html attributes
		 * @param boolean $double_encode
		 *        encode existing HTML characters
		 * @return string
		 */
		protected static function _textarea ($name, $body = '', array $attributes = NULL, $double_encode = TRUE)
		{
			// Set the input name
			$attributes['name'] = $name;

			// Add default rows and cols attributes (required)
			$attributes += array('rows' => 10,'cols' => 50);

			return '<textarea' . AVH2_Html::attributes($attributes) . '>' . esc_textarea($body) . '</textarea>';
		}

		/**
		 * Creates a select form input.
		 *
		 * @param string $name
		 *        input name
		 * @param array $options
		 *        available options
		 * @param mixed $selected
		 *        selected option string, or an array of
		 *        selected options
		 * @param array $attributes
		 *        html attributes
		 * @return string
		 */
		protected static function _select ($name, array $options = NULL, $selected = NULL, array $attributes = NULL)
		{
			// Set the input name
			$attributes['name'] = $name;

			if ( is_array($selected) ) {
				// This is a multi-select, god save us!
				$attributes['multiple'] = 'multiple';
			}

			if ( !is_array($selected) ) {
				if ( $selected === NULL ) {
					// Use an empty array
					$selected = array();
				} else {
					// Convert the selected options to an array
					$selected = array((string) $selected);
				}
			}

			if ( empty($options) ) {
				// There are no options
				$options = '';
			} else {
				foreach ( $options as $value => $name ) {
					if ( is_array($name) ) {
						// Create a new optgroup
						$group = array('label' => $value);

						// Create a new list of options
						$_options = array();

						foreach ( $name as $_value => $_name ) {
							// Force value to be string
							$_value = (string) $_value;

							// Create a new attribute set for this option
							$option = array('value' => $_value);

							if ( in_array($_value, $selected) ) {
								// This option is selected
								$option['selected'] = 'selected';
							}

							// Change the option to the HTML string
							$_options[] = '<option' . AVH2_Html::attributes($option) . '>' . $_name . '</option>';
						}

						// Compile the options into a string
						$_options = "\n" . implode("\n", $_options) . "\n";

						$options[$value] = '<optgroup' . AVH2_Html::attributes($group) . '>' . $_options . '</optgroup>';
					} else {
						// Force value to be string
						$value = (string) $value;

						// Create a new attribute set for this option
						$option = array('value' => $value);

						if ( in_array($value, $selected) ) {
							// This option is selected
							$option['selected'] = 'selected';
						}

						// Change the option to the HTML string
						$options[$value] = '<option' . AVH2_Html::attributes($option) . '>' . $name . '</option>';
					}
				}

				// Compile the options into a single string
				$options = "\n" . implode("\n", $options) . "\n";
			}

			return '<select' . AVH2_Html::attributes($attributes) . '>' . $options . '</select>';
		}

		/**
		 * Creates a submit form input.
		 *
		 * @param string $name
		 *        input name
		 * @param string $value
		 *        input value
		 * @param array $attributes
		 *        html attributes
		 * @return string
		 */
		protected static function _submit ($name, $value, array $attributes = NULL)
		{
			$attributes['type'] = 'submit';

			return AVH2_Form::input($name, $value, $attributes);
		}

		/**
		 * Creates a image form input.
		 *
		 * @param string $name
		 *        input name
		 * @param string $value
		 *        input value
		 * @param array $attributes
		 *        html attributes
		 * @param boolean $index
		 *        add index file to URL?
		 * @return string
		 */
		protected static function _image ($name, $value, array $attributes = NULL, $index = FALSE)
		{
			if ( !empty($attributes['src']) ) {
				if ( strpos($attributes['src'], '://') === FALSE ) {
					// Add the base URL
					$attributes['src'] = URL::base($index) . $attributes['src'];
				}
			}

			$attributes['type'] = 'image';

			return AVH2_Form::input($name, $value, $attributes);
		}

		/**
		 * Creates a button form input.
		 * Note that the body of a button is NOT escaped, to allow images and
		 * other HTML to be used.
		 *
		 * @param string $name
		 *        input name
		 * @param string $value
		 *        input value
		 * @param array $attributes
		 *        html attributes
		 * @return string
		 */
		protected static function _button ($name, $body, array $attributes = NULL)
		{
			// Set the input name
			$attributes['name'] = $name;

			return '<button' . AVH2_Html::attributes($attributes) . '>' . $body . '</button>';
		}

		/**
		 * Creates a form label.
		 * Label text is not automatically translated.
		 *
		 * @param string $input
		 *        target input
		 * @param string $text
		 *        label text
		 * @param array $attributes
		 *        html attributes
		 * @return string
		 */
		protected static function _label ($input, $text = NULL, array $attributes = NULL)
		{
			if ( $text === NULL ) {
				// Use the input name as the text
				$text = ucwords(preg_replace('/[\W_]+/', ' ', $input));
			}

			// Set the label target
			$attributes['for'] = $input;

			return '<label' . AVH2_Html::attributes($attributes) . '>' . $text . '</label>';
		}
	}
}