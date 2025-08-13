/**
 * WebSocket Client for Real-time Communication
 * This file demonstrates how to connect to Laravel broadcasting channels
 * 
 * Prerequisites:
 * - Pusher JS library: npm install pusher-js
 * - Laravel Echo: npm install laravel-echo
 */

import Pusher from 'pusher-js';
import Echo from 'laravel-echo';

// Configure Pusher
window.Pusher = Pusher;

// Initialize Laravel Echo
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    encrypted: true,
    authorizer: (channel, options) => {
        return {
            authorize: (socketId, callback) => {
                // Make a POST request to /broadcasting/auth
                fetch('/broadcasting/auth', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    },
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channel.name
                    })
                })
                .then(response => response.json())
                .then(data => {
                    callback(null, data);
                })
                .catch(error => {
                    callback(error);
                });
            }
        };
    }
});

/**
 * WebSocket Manager Class
 */
class WebSocketManager {
    constructor() {
        this.channels = new Map();
        this.eventListeners = new Map();
        this.isConnected = false;
        this.connectionAttempts = 0;
        this.maxReconnectAttempts = 5;
    }

    /**
     * Connect to customer channel
     */
    connectToCustomerChannel(customerId) {
        const channelName = `customer.${customerId}`;
        
        if (this.channels.has(channelName)) {
            return this.channels.get(channelName);
        }

        const channel = window.Echo.private(channelName);
        
        // Listen for order status updates
        channel.listen('.order.status.updated', (data) => {
            this.handleOrderStatusUpdate(data);
        });

        // Listen for delivery status changes
        channel.listen('.delivery.status.changed', (data) => {
            this.handleDeliveryStatusChange(data);
        });

        // Listen for driver location updates
        channel.listen('.driver.location.updated', (data) => {
            this.handleDriverLocationUpdate(data);
        });

        this.channels.set(channelName, channel);
        return channel;
    }

    /**
     * Connect to restaurant channel
     */
    connectToRestaurantChannel(restaurantBranchId) {
        const channelName = `restaurant.${restaurantBranchId}`;
        
        if (this.channels.has(channelName)) {
            return this.channels.get(channelName);
        }

        const channel = window.Echo.private(channelName);
        
        // Listen for new orders
        channel.listen('.order.new.placed', (data) => {
            this.handleNewOrder(data);
        });

        // Listen for order status updates
        channel.listen('.order.status.updated', (data) => {
            this.handleOrderStatusUpdate(data);
        });

        // Listen for delivery status changes
        channel.listen('.delivery.status.changed', (data) => {
            this.handleDeliveryStatusChange(data);
        });

        this.channels.set(channelName, channel);
        return channel;
    }

    /**
     * Connect to kitchen display channel
     */
    connectToKitchenChannel(restaurantBranchId) {
        const channelName = `kitchen.${restaurantBranchId}`;
        
        if (this.channels.has(channelName)) {
            return this.channels.get(channelName);
        }

        const channel = window.Echo.private(channelName);
        
        // Listen for new orders
        channel.listen('.order.new.placed', (data) => {
            this.handleKitchenNewOrder(data);
        });

        // Listen for kitchen order updates
        channel.listen('.kitchen.order.updated', (data) => {
            this.handleKitchenOrderUpdate(data);
        });

        this.channels.set(channelName, channel);
        return channel;
    }

    /**
     * Connect to driver channel
     */
    connectToDriverChannel(driverId) {
        const channelName = `driver.${driverId}`;
        
        if (this.channels.has(channelName)) {
            return this.channels.get(channelName);
        }

        const channel = window.Echo.private(channelName);
        
        // Listen for order assignments
        channel.listen('.order.status.updated', (data) => {
            this.handleDriverOrderUpdate(data);
        });

        // Listen for delivery status changes
        channel.listen('.delivery.status.changed', (data) => {
            this.handleDriverDeliveryUpdate(data);
        });

        this.channels.set(channelName, channel);
        return channel;
    }

    /**
     * Handle order status updates
     */
    handleOrderStatusUpdate(data) {
        console.log('Order status updated:', data);
        
        // Emit custom event for components to listen to
        window.dispatchEvent(new CustomEvent('orderStatusUpdated', {
            detail: data
        }));

        // Update UI based on status
        this.updateOrderStatusUI(data);
    }

    /**
     * Handle new orders
     */
    handleNewOrder(data) {
        console.log('New order received:', data);
        
        window.dispatchEvent(new CustomEvent('newOrderReceived', {
            detail: data
        }));

        // Show notification
        this.showNotification('New Order', `Order #${data.order_number} received from ${data.customer_name}`);
    }

    /**
     * Handle delivery status changes
     */
    handleDeliveryStatusChange(data) {
        console.log('Delivery status changed:', data);
        
        window.dispatchEvent(new CustomEvent('deliveryStatusChanged', {
            detail: data
        }));

        // Update delivery tracking UI
        this.updateDeliveryTrackingUI(data);
    }

    /**
     * Handle driver location updates
     */
    handleDriverLocationUpdate(data) {
        console.log('Driver location updated:', data);
        
        window.dispatchEvent(new CustomEvent('driverLocationUpdated', {
            detail: data
        }));

        // Update map if available
        this.updateDriverLocationOnMap(data);
    }

    /**
     * Handle kitchen order updates
     */
    handleKitchenOrderUpdate(data) {
        console.log('Kitchen order updated:', data);
        
        window.dispatchEvent(new CustomEvent('kitchenOrderUpdated', {
            detail: data
        }));

        // Update kitchen display
        this.updateKitchenDisplay(data);
    }

    /**
     * Handle driver order updates
     */
    handleDriverOrderUpdate(data) {
        console.log('Driver order update:', data);
        
        window.dispatchEvent(new CustomEvent('driverOrderUpdate', {
            detail: data
        }));
    }

    /**
     * Handle driver delivery updates
     */
    handleDriverDeliveryUpdate(data) {
        console.log('Driver delivery update:', data);
        
        window.dispatchEvent(new CustomEvent('driverDeliveryUpdate', {
            detail: data
        }));
    }

    /**
     * Update order status UI
     */
    updateOrderStatusUI(data) {
        const orderElement = document.querySelector(`[data-order-id="${data.order_id}"]`);
        if (orderElement) {
            const statusElement = orderElement.querySelector('.order-status');
            if (statusElement) {
                statusElement.textContent = data.new_status;
                statusElement.className = `order-status status-${data.new_status}`;
            }
        }
    }

    /**
     * Update delivery tracking UI
     */
    updateDeliveryTrackingUI(data) {
        const trackingElement = document.querySelector(`[data-order-id="${data.order_id}"]`);
        if (trackingElement) {
            const statusElement = trackingElement.querySelector('.delivery-status');
            if (statusElement) {
                statusElement.textContent = data.new_status;
                statusElement.className = `delivery-status status-${data.new_status}`;
            }
        }
    }

    /**
     * Update driver location on map
     */
    updateDriverLocationOnMap(data) {
        // Implementation depends on your mapping library
        if (window.driverMap && data.location) {
            window.driverMap.updateDriverLocation(data.driver_id, data.location);
        }
    }

    /**
     * Update kitchen display
     */
    updateKitchenDisplay(data) {
        const kitchenDisplay = document.querySelector('.kitchen-display');
        if (kitchenDisplay) {
            // Update kitchen display with new order information
            this.refreshKitchenOrders();
        }
    }

    /**
     * Show notification
     */
    showNotification(title, message) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, { body: message });
        } else if (window.toastr) {
            window.toastr.info(message, title);
        }
    }

    /**
     * Refresh kitchen orders
     */
    refreshKitchenOrders() {
        // Implement kitchen display refresh logic
        console.log('Refreshing kitchen display...');
    }

    /**
     * Disconnect from all channels
     */
    disconnect() {
        this.channels.forEach((channel, name) => {
            channel.unsubscribe();
        });
        this.channels.clear();
    }

    /**
     * Get connection status
     */
    getConnectionStatus() {
        return {
            isConnected: this.isConnected,
            channels: Array.from(this.channels.keys()),
            connectionAttempts: this.connectionAttempts
        };
    }
}

// Export the WebSocket manager
export default WebSocketManager;

// Usage example:
/*
const wsManager = new WebSocketManager();

// Connect to customer channel
wsManager.connectToCustomerChannel(123);

// Connect to restaurant channel
wsManager.connectToRestaurantChannel(456);

// Listen for events
window.addEventListener('orderStatusUpdated', (event) => {
    console.log('Order status updated:', event.detail);
});

window.addEventListener('newOrderReceived', (event) => {
    console.log('New order received:', event.detail);
});
*/
