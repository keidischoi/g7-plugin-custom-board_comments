/**
 * 떠 있는 댓글 도구 막대 접기/펼치기.
 *
 * - 접힌 상태에서는 스티커 버튼만 보입니다.
 * - 마우스를 올리거나(hover) 키보드 포커스가 들어오면 펼칩니다.
 * - 포인터가 나가고, 포커스도 밖에 있고, 막대에서 연 스티커 창도 닫혀 있으면
 *   COLLAPSE_DELAY_MS 뒤에 다시 접습니다(깜빡임 방지).
 * - 터치: 접힌 막대를 탭하면 펼치고, 바깥을 탭하면 접습니다.
 */

export const COLLAPSE_DELAY_MS = 300;

type CollapseState = {
    enabled: boolean;
    hover: boolean;
    touchOpen: boolean;
    timer: number | null;
    abort: AbortController;
};

const states = new WeakMap<HTMLElement, CollapseState>();

function stickerButton(toolbar: HTMLElement): HTMLElement | null {
    return toolbar.querySelector<HTMLElement>('[data-cbc-sticker]');
}

function panelOpen(): boolean {
    return document.querySelector('[data-cbc-stickers]') !== null;
}

function focusInside(toolbar: HTMLElement): boolean {
    const active = document.activeElement;
    if (!(active instanceof Node)) {
        return false;
    }
    if (toolbar.contains(active)) {
        return true;
    }
    const panel = document.querySelector('[data-cbc-stickers]');
    return panel instanceof Node && panel.contains(active);
}

function canCollapse(toolbar: HTMLElement, state: CollapseState): boolean {
    const sticker = stickerButton(toolbar);
    // 스티커가 꺼져 있으면 접었을 때 보일 항목이 없으므로 항상 펼쳐 둡니다.
    return state.enabled && sticker !== null && !sticker.hidden;
}

function setExpanded(toolbar: HTMLElement, expanded: boolean): void {
    toolbar.classList.toggle('is-collapsed', !expanded);
    toolbar.setAttribute('aria-expanded', expanded ? 'true' : 'false');
}

function clearTimer(state: CollapseState): void {
    if (state.timer !== null) {
        window.clearTimeout(state.timer);
        state.timer = null;
    }
}

function shouldStayOpen(toolbar: HTMLElement, state: CollapseState): boolean {
    return state.hover || state.touchOpen || focusInside(toolbar) || panelOpen();
}

function expand(toolbar: HTMLElement, state: CollapseState): void {
    clearTimer(state);
    if (canCollapse(toolbar, state)) {
        setExpanded(toolbar, true);
    }
}

function scheduleCollapse(toolbar: HTMLElement, state: CollapseState): void {
    clearTimer(state);
    if (!canCollapse(toolbar, state)) {
        return;
    }
    state.timer = window.setTimeout(() => {
        state.timer = null;
        if (!toolbar.isConnected || !canCollapse(toolbar, state)) {
            return;
        }
        if (shouldStayOpen(toolbar, state)) {
            return;
        }
        setExpanded(toolbar, false);
    }, COLLAPSE_DELAY_MS);
}

function isTouch(event: PointerEvent): boolean {
    return event.pointerType === 'touch';
}

function bind(toolbar: HTMLElement, state: CollapseState): void {
    const { signal } = state.abort;

    toolbar.addEventListener('pointerenter', (event) => {
        if (isTouch(event)) {
            return;
        }
        state.hover = true;
        expand(toolbar, state);
    }, { signal });

    toolbar.addEventListener('pointerleave', (event) => {
        if (isTouch(event)) {
            return;
        }
        state.hover = false;
        scheduleCollapse(toolbar, state);
    }, { signal });

    toolbar.addEventListener('focusin', () => {
        expand(toolbar, state);
    }, { signal });

    toolbar.addEventListener('focusout', () => {
        scheduleCollapse(toolbar, state);
    }, { signal });

    // 접힌 막대의 빈 곳을 탭하면 펼치기만 합니다. 스티커 버튼 탭은 그대로 스티커 창을 엽니다.
    toolbar.addEventListener('click', (event) => {
        if (!canCollapse(toolbar, state) || !toolbar.classList.contains('is-collapsed')) {
            return;
        }
        state.touchOpen = true;
        expand(toolbar, state);
        const target = event.target instanceof Element ? event.target : null;
        if (!target?.closest('[data-cbc-sticker]')) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, { capture: true, signal });

    toolbar.addEventListener('pointerdown', (event) => {
        if (isTouch(event)) {
            state.touchOpen = true;
            expand(toolbar, state);
        }
    }, { signal });

    document.addEventListener('pointerdown', (event) => {
        const target = event.target;
        if (target instanceof Node && toolbar.contains(target)) {
            return;
        }
        const panel = document.querySelector('[data-cbc-stickers]');
        if (target instanceof Node && panel instanceof Node && panel.contains(target)) {
            return;
        }
        state.touchOpen = false;
        if (isTouch(event)) {
            state.hover = false;
        }
        scheduleCollapse(toolbar, state);
    }, { capture: true, signal });

    // 스티커 창이 닫히면(cbc-close는 버블링하지 않으므로 capture로 받음) 다시 접을지 봅니다.
    document.addEventListener('cbc-close', () => {
        scheduleCollapse(toolbar, state);
    }, { capture: true, signal });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !focusInside(toolbar) || panelOpen()) {
            return;
        }
        state.touchOpen = false;
        state.hover = false;
        clearTimer(state);
        if (canCollapse(toolbar, state)) {
            setExpanded(toolbar, false);
        }
    }, { signal });
}

/**
 * 도구 막대에 접기 동작을 붙입니다. 여러 번 불러도 한 번만 묶고, `enabled`만 갱신합니다.
 */
export function bindToolbarCollapse(toolbar: HTMLElement, enabled: boolean): void {
    let state = states.get(toolbar);
    if (!state) {
        state = {
            enabled,
            hover: false,
            touchOpen: false,
            timer: null,
            abort: new AbortController(),
        };
        states.set(toolbar, state);
        bind(toolbar, state);
        state.enabled = enabled;
        const on = canCollapse(toolbar, state);
        toolbar.classList.toggle('cbc-toolbar--collapsible', on);
        if (on) {
            setExpanded(toolbar, shouldStayOpen(toolbar, state));
        } else {
            toolbar.removeAttribute('aria-expanded');
        }
        return;
    }

    state.enabled = enabled;
    const on = canCollapse(toolbar, state);
    const was = toolbar.classList.contains('cbc-toolbar--collapsible');
    toolbar.classList.toggle('cbc-toolbar--collapsible', on);
    if (!on) {
        clearTimer(state);
        toolbar.classList.remove('is-collapsed');
        toolbar.removeAttribute('aria-expanded');
        return;
    }
    if (!was) {
        setExpanded(toolbar, shouldStayOpen(toolbar, state));
    }
}

/** 도구 막대를 없앨 때 문서에 건 리스너를 풉니다. */
export function unbindToolbarCollapse(toolbar: HTMLElement): void {
    const state = states.get(toolbar);
    if (!state) {
        return;
    }
    clearTimer(state);
    state.abort.abort();
    states.delete(toolbar);
}
