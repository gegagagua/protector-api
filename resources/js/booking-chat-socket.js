import { io } from 'socket.io-client';

/**
 * Real-time booking chat (Socket.IO). Messages are still created via REST
 * (POST /api/client/bookings/{id}/messages or /api/security/orders/{id}/messages);
 * this socket receives `message.sent` (chat) and `location.updated` (GPS: latitude, longitude,
 * google_maps_url) when the Socket.IO bridge is configured server-side.
 *
 * @param {object} options
 * @param {string} options.url - Base URL of the Socket.IO server (e.g. import.meta.env.VITE_SOCKET_URL)
 * @param {string} options.token - Sanctum bearer token (client or security)
 * @param {number} options.bookingId
 * @param {(payload: object) => void} [options.onMessage]
 * @param {(payload: object) => void} [options.onLocation] - guard GPS updates for Google Maps
 * @param {(err: Error) => void} [options.onConnectError]
 */
export function createBookingChatSocket({ url, token, bookingId, onMessage, onLocation, onConnectError }) {
    const socket = io(url, {
        auth: { token },
        transports: ['websocket', 'polling'],
    });

    socket.on('connect', () => {
        socket.emit('join_booking', { booking_id: Number(bookingId) }, (ack) => {
            if (ack?.error) {
                onConnectError?.(new Error(ack.error));
            }
        });
    });

    socket.on('message.sent', (payload) => {
        onMessage?.(payload);
    });

    socket.on('location.updated', (payload) => {
        onLocation?.(payload);
    });

    socket.on('connect_error', (err) => {
        onConnectError?.(err);
    });

    return socket;
}
