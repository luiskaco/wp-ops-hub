<?php
/**
 * Herramientas de WooCommerce para WP MCP Agent Bridge.
 *
 * @package WP_MCP_Agent_Bridge
 */

defined( 'ABSPATH' ) || exit;

trait WP_MCP_Bridge_Tools_Woo {

	/**
	 * Registra el catálogo de herramientas de WooCommerce si el plugin está activo.
	 *
	 * @return array
	 */
	public static function get_woo_tools() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		return array(
			// Listar productos
			'woo-list-products' => self::define_tool(
				'woo-list-products',
				'Listar productos de WooCommerce',
				'Devuelve productos de la tienda con filtros por estado, stock, categoría y búsqueda.',
				'woocommerce',
				true,
				array( __CLASS__, 'tool_woo_list_products' ),
				array(
					'status'       => self::schema_string( 'Estado (publish, draft, any). Por defecto any.', 20 ),
					'stock_status' => self::schema_string( 'Estado de stock (instock, outofstock, onbackorder).', 20 ),
					'category'     => self::schema_string( 'Slug de categoría de producto.', 50 ),
					'search'       => self::schema_string( 'Término de búsqueda.', 100 ),
					'limit'        => self::schema_int( 'Cantidad de productos a retornar (1-50). Por defecto 20.', 1, 50 ),
					'page'         => self::schema_int( 'Número de página. Por defecto 1.', 1 ),
				),
				array(),
				array( 'cap' => 'manage_woocommerce' )
			),

			// Obtener un producto
			'woo-get-product' => self::define_tool(
				'woo-get-product',
				'Obtener producto de WooCommerce',
				'Devuelve detalles completos de un producto por ID: precios, SKU, stock, dimensiones y galería.',
				'woocommerce',
				true,
				array( __CLASS__, 'tool_woo_get_product' ),
				array(
					'id' => self::schema_int( 'ID del producto.', 1 ),
				),
				array( 'id' ),
				array( 'cap' => 'manage_woocommerce' )
			),

			// Crear producto
			'woo-create-product' => self::define_tool(
				'woo-create-product',
				'Crear producto en WooCommerce',
				'Crea un nuevo producto simple con precio, SKU, stock e imagen destacada.',
				'woocommerce',
				false,
				array( __CLASS__, 'tool_woo_create_product' ),
				array(
					'name'          => self::schema_string( 'Nombre del producto.', 200 ),
					'regular_price' => self::schema_string( 'Precio normal (ej: 19.99).', 20 ),
					'sale_price'    => self::schema_string( 'Precio de oferta opcional (ej: 14.99).', 20 ),
					'description'   => self::schema_string( 'Descripción completa.' ),
					'short_desc'    => self::schema_string( 'Descripción corta.' ),
					'sku'           => self::schema_string( 'Código de referencia / SKU.', 50 ),
					'stock_qty'     => self::schema_int( 'Cantidad en inventario (si maneja stock).' ),
					'image_id'      => self::schema_int( 'ID de attachment de la imagen destacada.', 1 ),
					'status'        => self::schema_string( 'Estado (publish, draft). Por defecto draft.', 20 ),
				),
				array( 'name' ),
				array( 'cap' => 'manage_woocommerce' )
			),

			// Actualizar producto
			'woo-update-product' => self::define_tool(
				'woo-update-product',
				'Actualizar producto de WooCommerce',
				'Actualiza precios, stock, descripción o imágenes de un producto existente.',
				'woocommerce',
				false,
				array( __CLASS__, 'tool_woo_update_product' ),
				array(
					'id'            => self::schema_int( 'ID del producto.', 1 ),
					'name'          => self::schema_string( 'Nuevo nombre.', 200 ),
					'regular_price' => self::schema_string( 'Nuevo precio normal.', 20 ),
					'sale_price'    => self::schema_string( 'Nuevo precio de oferta.', 20 ),
					'stock_qty'     => self::schema_int( 'Nueva cantidad de stock.' ),
					'status'        => self::schema_string( 'Nuevo estado (publish, draft).', 20 ),
					'image_id'      => self::schema_int( 'Nuevo ID de imagen destacada.', 1 ),
				),
				array( 'id' ),
				array( 'cap' => 'manage_woocommerce' )
			),

			// Listar pedidos
			'woo-list-orders' => self::define_tool(
				'woo-list-orders',
				'Listar pedidos de WooCommerce',
				'Devuelve lista de pedidos con filtros por estado y paginación.',
				'woocommerce',
				true,
				array( __CLASS__, 'tool_woo_list_orders' ),
				array(
					'status' => self::schema_string( 'Estado del pedido (processing, completed, on-hold, pending, cancelled, any).', 30 ),
					'limit'  => self::schema_int( 'Cantidad de pedidos (1-50). Por defecto 20.', 1, 50 ),
					'page'   => self::schema_int( 'Número de página. Por defecto 1.', 1 ),
				),
				array(),
				array( 'cap' => 'manage_woocommerce' )
			),

			// Obtener pedido
			'woo-get-order' => self::define_tool(
				'woo-get-order',
				'Obtener pedido de WooCommerce',
				'Devuelve detalle completo de un pedido: items comprados, totales, datos de cliente y dirección.',
				'woocommerce',
				true,
				array( __CLASS__, 'tool_woo_get_order' ),
				array(
					'id' => self::schema_int( 'ID del pedido.', 1 ),
				),
				array( 'id' ),
				array( 'cap' => 'manage_woocommerce' )
			),

			// Actualizar estado de pedido
			'woo-update-order-status' => self::define_tool(
				'woo-update-order-status',
				'Actualizar estado de pedido en WooCommerce',
				'Cambia el estado de un pedido (ej: processing a completed) y añade una nota opcional.',
				'woocommerce',
				false,
				array( __CLASS__, 'tool_woo_update_order_status' ),
				array(
					'id'     => self::schema_int( 'ID del pedido.', 1 ),
					'status' => self::schema_string( 'Nuevo estado (processing, completed, on-hold, cancelled, refunded).', 30 ),
					'note'   => self::schema_string( 'Nota opcional para registrar en el cambio de estado.', 300 ),
				),
				array( 'id', 'status' ),
				array( 'cap' => 'manage_woocommerce' )
			),
		);
	}

	/**
	 * Callback para woo-list-products.
	 */
	public static function tool_woo_list_products( $args ) {
		$status   = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'any';
		$limit    = self::clamp_limit( isset( $args['limit'] ) ? $args['limit'] : 20 );
		$page     = self::clamp_page( isset( $args['page'] ) ? $args['page'] : 1 );
		$search   = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';

		$query_args = array(
			'status'   => $status,
			'limit'    => $limit,
			'page'     => $page,
			'paginate' => true,
		);

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		if ( ! empty( $args['stock_status'] ) ) {
			$query_args['stock_status'] = sanitize_key( $args['stock_status'] );
		}

		if ( ! empty( $args['category'] ) ) {
			$query_args['category'] = array( sanitize_key( $args['category'] ) );
		}

		$results  = wc_get_products( $query_args );
		$products = array();

		foreach ( $results->products as $p ) {
			$products[] = array(
				'id'            => $p->get_id(),
				'name'          => $p->get_name(),
				'sku'           => $p->get_sku(),
				'price'         => $p->get_price(),
				'regular_price' => $p->get_regular_price(),
				'sale_price'    => $p->get_sale_price(),
				'stock_status'  => $p->get_stock_status(),
				'stock_qty'     => $p->get_stock_quantity(),
				'status'        => $p->get_status(),
				'url'           => $p->get_permalink(),
			);
		}

		return array(
			'products'    => $products,
			'total'       => (int) $results->total,
			'total_pages' => (int) $results->max_num_pages,
			'page'        => $page,
		);
	}

	/**
	 * Callback para woo-get-product.
	 */
	public static function tool_woo_get_product( $args ) {
		$id      = isset( $args['id'] ) ? (int) $args['id'] : 0;
		$product = wc_get_product( $id );

		if ( ! $product ) {
			return self::mcp_error( 'product_not_found', 'Producto de WooCommerce no encontrado.' );
		}

		return array(
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'slug'              => $product->get_slug(),
			'type'              => $product->get_type(),
			'status'            => $product->get_status(),
			'sku'               => $product->get_sku(),
			'price'             => $product->get_price(),
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'stock_status'      => $product->get_stock_status(),
			'stock_qty'         => $product->get_stock_quantity(),
			'description'       => $product->get_description(),
			'short_description' => $product->get_short_description(),
			'image_url'         => wp_get_attachment_url( $product->get_image_id() ),
			'gallery_ids'       => $product->get_gallery_image_ids(),
			'categories'        => wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) ),
			'url'               => $product->get_permalink(),
		);
	}

	/**
	 * Callback para woo-create-product.
	 */
	public static function tool_woo_create_product( $args ) {
		$name = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';
		if ( '' === $name ) {
			return self::mcp_error( 'missing_name', 'El nombre del producto es obligatorio.' );
		}

		$product = new WC_Product_Simple();
		$product->set_name( $name );

		if ( isset( $args['regular_price'] ) ) {
			$product->set_regular_price( wc_format_decimal( $args['regular_price'] ) );
		}
		if ( isset( $args['sale_price'] ) ) {
			$product->set_sale_price( wc_format_decimal( $args['sale_price'] ) );
		}
		if ( isset( $args['description'] ) ) {
			$product->set_description( (string) $args['description'] );
		}
		if ( isset( $args['short_desc'] ) ) {
			$product->set_short_description( sanitize_textarea_field( $args['short_desc'] ) );
		}
		if ( isset( $args['sku'] ) ) {
			$product->set_sku( sanitize_text_field( $args['sku'] ) );
		}
		if ( isset( $args['status'] ) ) {
			$product->set_status( sanitize_key( $args['status'] ) );
		} else {
			$product->set_status( 'draft' );
		}
		if ( isset( $args['stock_qty'] ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $args['stock_qty'] );
		}
		if ( isset( $args['image_id'] ) ) {
			$product->set_image_id( (int) $args['image_id'] );
		}

		$product_id = $product->save();

		return array(
			'id'      => $product_id,
			'name'    => $product->get_name(),
			'price'   => $product->get_price(),
			'status'  => $product->get_status(),
			'url'     => $product->get_permalink(),
			'message' => 'Producto de WooCommerce creado exitosamente.',
		);
	}

	/**
	 * Callback para woo-update-product.
	 */
	public static function tool_woo_update_product( $args ) {
		$id      = isset( $args['id'] ) ? (int) $args['id'] : 0;
		$product = wc_get_product( $id );

		if ( ! $product ) {
			return self::mcp_error( 'product_not_found', 'Producto de WooCommerce no encontrado.' );
		}

		if ( isset( $args['name'] ) ) {
			$product->set_name( sanitize_text_field( $args['name'] ) );
		}
		if ( isset( $args['regular_price'] ) ) {
			$product->set_regular_price( wc_format_decimal( $args['regular_price'] ) );
		}
		if ( isset( $args['sale_price'] ) ) {
			$product->set_sale_price( wc_format_decimal( $args['sale_price'] ) );
		}
		if ( isset( $args['status'] ) ) {
			$product->set_status( sanitize_key( $args['status'] ) );
		}
		if ( isset( $args['stock_qty'] ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $args['stock_qty'] );
		}
		if ( isset( $args['image_id'] ) ) {
			$product->set_image_id( (int) $args['image_id'] );
		}

		$product->save();

		return array(
			'id'      => $id,
			'name'    => $product->get_name(),
			'price'   => $product->get_price(),
			'status'  => $product->get_status(),
			'url'     => $product->get_permalink(),
			'message' => 'Producto de WooCommerce actualizado.',
		);
	}

	/**
	 * Callback para woo-list-orders.
	 */
	public static function tool_woo_list_orders( $args ) {
		$status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'any';
		$limit  = self::clamp_limit( isset( $args['limit'] ) ? $args['limit'] : 20 );
		$page   = self::clamp_page( isset( $args['page'] ) ? $args['page'] : 1 );

		$orders_query = wc_get_orders(
			array(
				'status'   => $status,
				'limit'    => $limit,
				'page'     => $page,
				'paginate' => true,
			)
		);

		$orders = array();
		foreach ( $orders_query->orders as $o ) {
			$orders[] = array(
				'id'             => $o->get_id(),
				'order_number'   => $o->get_order_number(),
				'status'         => $o->get_status(),
				'total'          => $o->get_total(),
				'currency'       => $o->get_currency(),
				'customer_name'  => $o->get_formatted_billing_full_name(),
				'customer_email' => $o->get_billing_email(),
				'date_created'   => $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
			);
		}

		return array(
			'orders'      => $orders,
			'total'       => (int) $orders_query->total,
			'total_pages' => (int) $orders_query->max_num_pages,
			'page'        => $page,
		);
	}

	/**
	 * Callback para woo-get-order.
	 */
	public static function tool_woo_get_order( $args ) {
		$id    = isset( $args['id'] ) ? (int) $args['id'] : 0;
		$order = wc_get_order( $id );

		if ( ! $order ) {
			return self::mcp_error( 'order_not_found', 'Pedido de WooCommerce no encontrado.' );
		}

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'product_id' => $item->get_product_id(),
				'name'       => $item->get_name(),
				'quantity'   => $item->get_quantity(),
				'subtotal'   => $item->get_subtotal(),
				'total'      => $item->get_total(),
			);
		}

		return array(
			'id'             => $order->get_id(),
			'order_number'   => $order->get_order_number(),
			'status'         => $order->get_status(),
			'total'          => $order->get_total(),
			'shipping_total' => $order->get_shipping_total(),
			'payment_method' => $order->get_payment_method_title(),
			'customer'       => array(
				'name'  => $order->get_formatted_billing_full_name(),
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone(),
			),
			'billing'        => $order->get_address( 'billing' ),
			'shipping'       => $order->get_address( 'shipping' ),
			'items'          => $items,
			'date_created'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
		);
	}

	/**
	 * Callback para woo-update-order-status.
	 */
	public static function tool_woo_update_order_status( $args ) {
		$id     = isset( $args['id'] ) ? (int) $args['id'] : 0;
		$status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		$note   = isset( $args['note'] ) ? sanitize_text_field( $args['note'] ) : '';

		$order = wc_get_order( $id );
		if ( ! $order ) {
			return self::mcp_error( 'order_not_found', 'Pedido de WooCommerce no encontrado.' );
		}

		$order->update_status( $status, $note );

		return array(
			'id'      => $id,
			'status'  => $order->get_status(),
			'message' => 'Estado de pedido actualizado correctamente.',
		);
	}
}
