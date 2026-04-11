import path from 'path';
import { fileURLToPath } from 'url';
import dotenv from 'dotenv';
import express from 'express';
import http from 'http';
import cors from 'cors';
import { Server } from 'socket.io';
import axios from 'axios';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.resolve(__dirname, '..', '.env') });

const PORT = Number(process.env.SOCKET_SERVER_PORT || 6001);
const LARAVEL_URL = (process.env.LARAVEL_URL || process.env.APP_URL || 'http://127.0.0.1:8000').replace(
    /\/$/,
    '',
);
const INTERNAL_SECRET = process.env.SOCKET_INTERNAL_SECRET || '';

const app = express();
app.use(express.json());
app.use(cors({ origin: true, credentials: true }));

const httpServer = http.createServer(app);
const io = new Server(httpServer, {
    cors: { origin: true, credentials: true },
});

app.post('/internal/emit', (req, res) => {
    if (!INTERNAL_SECRET || req.body?.secret !== INTERNAL_SECRET) {
        return res.status(401).json({ error: 'Unauthorized' });
    }
    const bookingId = req.body?.booking_id;
    const payload = req.body?.payload;
    const eventName = typeof req.body?.event === 'string' && req.body.event.length > 0 ? req.body.event : 'message.sent';
    if (!bookingId || !payload) {
        return res.status(422).json({ error: 'booking_id and payload required' });
    }
    io.to(`booking:${bookingId}`).emit(eventName, payload);
    return res.json({ ok: true, event: eventName });
});

const api = (token) =>
    axios.create({
        baseURL: `${LARAVEL_URL}/api`,
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        validateStatus: () => true,
    });

async function resolveActor(token) {
    const client = api(token);
    const clientMe = await client.get('/client/me');
    if (clientMe.status === 200 && clientMe.data?.status === 'success') {
        return { role: 'client', token };
    }
    const securityOrders = await client.get('/security/orders', { params: { per_page: 1 } });
    if (securityOrders.status === 200 && securityOrders.data?.status === 'success') {
        return { role: 'security', token };
    }
    return null;
}

async function canJoinBooking(role, token, bookingId) {
    const client = api(token);
    if (role === 'client') {
        const r = await client.get(`/client/bookings/${bookingId}`);
        return r.status === 200 && r.data?.status === 'success';
    }
    if (role === 'security') {
        const r = await client.get(`/security/orders/${bookingId}`);
        return r.status === 200 && r.data?.status === 'success';
    }
    return false;
}

io.use(async (socket, next) => {
    const token = socket.handshake.auth?.token ?? socket.handshake.query?.token;
    if (!token || typeof token !== 'string') {
        return next(new Error('auth token required'));
    }
    const actor = await resolveActor(token);
    if (!actor) {
        return next(new Error('invalid or unsupported token'));
    }
    socket.data.actor = actor;
    next();
});

io.on('connection', (socket) => {
    socket.on('join_booking', async (payload, cb) => {
        const bookingId = Number(payload?.booking_id ?? payload?.bookingId);
        if (!Number.isFinite(bookingId) || bookingId < 1) {
            cb?.({ error: 'invalid booking_id' });
            return;
        }
        const { role, token } = socket.data.actor;
        try {
            if (!(await canJoinBooking(role, token, bookingId))) {
                cb?.({ error: 'forbidden' });
                return;
            }
            const room = `booking:${bookingId}`;
            await socket.join(room);
            cb?.({ ok: true, room });
        } catch {
            cb?.({ error: 'join_failed' });
        }
    });

    socket.on('leave_booking', (payload, cb) => {
        const bookingId = Number(payload?.booking_id ?? payload?.bookingId);
        if (Number.isFinite(bookingId) && bookingId > 0) {
            socket.leave(`booking:${bookingId}`);
        }
        cb?.({ ok: true });
    });
});

httpServer.listen(PORT, () => {
    console.log(`Socket.IO chat on :${PORT} → Laravel ${LARAVEL_URL}`);
});
