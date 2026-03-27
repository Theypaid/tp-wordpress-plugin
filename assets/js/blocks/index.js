( function () {
	'use strict';

	if ( typeof wc === 'undefined' || ! wc.wcBlocksRegistry || ! wc.wcBlocksRegistry.registerPaymentMethod ) {
		return;
	}

	var registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;
	var createElement         = wp.element.createElement;
	var useState              = wp.element.useState;
	var useEffect             = wp.element.useEffect;
	var getSetting            = wc.wcSettings.getSetting;
	var decodeEntities        = wp.htmlEntities.decodeEntities;

	var settings = getSetting( 'theypaid_data', {} );
	var title    = decodeEntities( settings.title || 'Pay with Crypto' );
	var assets   = settings.assets || [];

	var Label = function () {
		return createElement( 'span', null, title );
	};

	var CoinSelector = function ( props ) {
		var eventRegistration = props.eventRegistration;
		var emitResponse      = props.emitResponse;
		var onPaymentSetup    = eventRegistration.onPaymentSetup;

		var initialAssetId = assets.length > 0 ? ( assets[ 0 ].assetId || '' ) : '';
		var state          = useState( initialAssetId );
		var selectedId     = state[ 0 ];
		var setSelectedId  = state[ 1 ];

		useEffect( function () {
			var unsubscribe = onPaymentSetup( function () {
				if ( ! selectedId ) {
					return {
						type: emitResponse.responseTypes.ERROR,
						message: 'Please select a cryptocurrency to pay with.',
					};
				}
				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							payment_method: 'theypaid',
							theypaid_asset_id: selectedId,
						},
					},
				};
			} );
			return unsubscribe;
		}, [ selectedId, onPaymentSetup, emitResponse.responseTypes.SUCCESS, emitResponse.responseTypes.ERROR ] );

		if ( ! assets.length ) {
			return createElement(
				'p',
				{ className: 'theypaid-error' },
				'No cryptocurrencies are currently available.'
			);
		}

		return createElement(
			'div',
			{ className: 'theypaid-coin-selector' },
			createElement(
				'p',
				{ className: 'theypaid-coin-selector__label' },
				'Select cryptocurrency:'
			),
			createElement(
				'div',
				{ className: 'theypaid-coin-selector__grid' },
				assets.map( function ( asset, index ) {
					var assetId = asset.assetId || '';
					var symbol  = asset.symbol || '';
					var network = asset.network || '';
					var logoUrl = '';

					if ( asset.logo ) {
						logoUrl = ( asset.logo.medium && asset.logo.medium.url )
							? asset.logo.medium.url
							: ( asset.logo.original && asset.logo.original.url )
								? asset.logo.original.url
								: '';
					}

					var isSelected = selectedId === assetId;

					return createElement(
						'label',
						{
							key: assetId || index,
							className: 'theypaid-coin-selector__item',
						},
						createElement( 'input', {
							type: 'radio',
							name: 'theypaid_asset_id',
							value: assetId,
							checked: isSelected,
							onChange: function () {
								setSelectedId( assetId );
							},
						} ),
						createElement(
							'span',
							{ className: 'theypaid-coin-selector__card' },
							logoUrl
								? createElement( 'img', {
									src: logoUrl,
									alt: symbol,
									className: 'theypaid-coin-selector__logo',
									width: 32,
									height: 32,
								} )
								: null,
							createElement(
								'span',
								{ className: 'theypaid-coin-selector__info' },
								createElement(
									'span',
									{ className: 'theypaid-coin-selector__symbol' },
									symbol
								),
								createElement(
									'span',
									{ className: 'theypaid-coin-selector__network' },
									network
								)
							)
						)
					);
				} )
			)
		);
	};

	registerPaymentMethod( {
		name: 'theypaid',
		label: createElement( Label, null ),
		content: createElement( CoinSelector, null ),
		edit: createElement( CoinSelector, null ),
		canMakePayment: function () {
			return assets.length > 0;
		},
		ariaLabel: title,
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )();
