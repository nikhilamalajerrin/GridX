import Service, { inject as service } from '@ember/service';
import config from 'ember-get-config';

/**
 * Plain-fetch client for the GridX quote pipeline (quote -> approval ->
 * payment -> dispatch). Not an ember-data model — this is a small, specific
 * action surface, not a general CRUD resource.
 */
export default class QuotesService extends Service {
    @service session;
    @service notifications;

    get authToken() {
        const authenticated = this.session?.data?.authenticated;
        if (authenticated?.token) {
            return authenticated.token;
        }

        try {
            const stored = JSON.parse(window.localStorage.getItem('ember_simple_auth-session'));
            return stored?.authenticated?.token;
        } catch (e) {
            return undefined;
        }
    }

    get baseUrl() {
        return `${config.API.host}/${config.API.namespace}/quotes`;
    }

    async request(path, method = 'GET', body) {
        const response = await fetch(`${this.baseUrl}${path}`, {
            method,
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                Authorization: `Bearer ${this.authToken}`,
            },
            body: body ? JSON.stringify(body) : undefined,
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(payload.error || `Request failed (${response.status})`);
        }

        return payload;
    }

    async fetchAll() {
        const { quotes } = await this.request('/');
        return quotes;
    }

    async approve(id) {
        return this.request(`/${id}/approve`, 'POST');
    }

    async reject(id) {
        return this.request(`/${id}/reject`, 'POST');
    }

    async markPaid(id) {
        return this.request(`/${id}/mark-paid`, 'POST');
    }

    async dispatch(id) {
        return this.request(`/${id}/dispatch`, 'POST');
    }

    async update(id, data) {
        return this.request(`/${id}`, 'PUT', data);
    }

    /**
     * The PDF endpoint requires the same Bearer-token auth as every other
     * quote request — a plain `window.open(url)` new-tab navigation can't
     * carry that header (browsers only send cookies automatically on a top-
     * level navigation), which is why it was failing with "Unauthenticated."
     *
     * Two things had to be true at once, and getting only one right still
     * left a blank tab:
     *  1. The tab must open SYNCHRONOUSLY inside the click handler — once
     *     you cross an `await`, the browser's brief "user activation" window
     *     closes and a later `window.open`/`link.click()` gets silently
     *     popup-blocked (confirmed: it "succeeded" but the tab stayed blank).
     *  2. You can't just set `.location` on that tab to a blob: URL created
     *     in a different document later — Chrome won't navigate a separate
     *     window reference to another document's blob URL. Writing an
     *     `<embed>` referencing the blob INTO that already-open tab's own
     *     document works, because it's content rendered inside that tab's
     *     document, not a top-level navigation of it.
     */
    openPdf(id) {
        const tab = window.open('', '_blank');
        if (tab) {
            tab.document.write('<body style="margin:0;font-family:sans-serif;color:#667085;display:flex;align-items:center;justify-content:center;height:100vh;">Loading PDF&hellip;</body>');
        }

        fetch(`${this.baseUrl}/${id}/pdf`, {
            credentials: 'include',
            headers: { Authorization: `Bearer ${this.authToken}` },
        })
            .then(async (response) => {
                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    throw new Error(payload.error || `Failed to load PDF (${response.status})`);
                }
                return response.blob();
            })
            .then((blob) => {
                const blobUrl = URL.createObjectURL(blob);
                if (tab && !tab.closed) {
                    tab.document.body.innerHTML = `<embed src="${blobUrl}" type="application/pdf" width="100%" height="100%" style="border:none;position:fixed;inset:0;" />`;
                } else {
                    window.location = blobUrl;
                }
                setTimeout(() => URL.revokeObjectURL(blobUrl), 5 * 60 * 1000);
            })
            .catch((e) => {
                tab?.close();
                this.notifications?.error?.(e.message || 'Failed to open PDF.');
            });
    }

    async runAction(actionName, id, method) {
        try {
            const result = await method(id);
            this.notifications?.success?.(`Quote ${actionName}.`);
            return result;
        } catch (e) {
            this.notifications?.error?.(e.message || `Failed to ${actionName} quote.`);
            throw e;
        }
    }
}
