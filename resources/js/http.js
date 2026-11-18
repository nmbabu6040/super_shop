// Small fetch wrapper: always sends the CSRF token and parses JSON.
// Backend remains the source of truth; this never calculates prices or stock.
const csrfToken =
    document.querySelector('meta[name="csrf-token"]')?.content ?? "";

export async function requestJson(
    url,
    { method = "GET", body = null, headers = {} } = {},
) {
    const response = await fetch(url, {
        method,
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN": csrfToken,
            ...(body !== null ? { "Content-Type": "application/json" } : {}),
            ...headers,
        },
        body: body !== null ? JSON.stringify(body) : null,
    });

    const payload =
        response.status === 204
            ? null
            : await response.json().catch(() => null);

    if (!response.ok) {
        const requestError = new Error(payload?.message ?? response.statusText);
        requestError.status = response.status;
        requestError.payload = payload;
        throw requestError;
    }

    return payload;
}
