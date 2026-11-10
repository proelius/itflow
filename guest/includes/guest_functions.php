<?php

/*
 * Helpers for the pages a client opens from an emailed link. They show the
 * same things as the agent-side helpers in functions/billing.php, but with
 * literal t() calls so the text follows the client's language (the agent-side
 * helpers stay as they are).
 */

/** The small badge saying how late or how soon an invoice is due - like invoiceDuePill(), in the client's language. */
function guestDuePill($state) {
    if (!in_array($state['state'], ['open', 'overdue'], true)) {
        return '';
    }
    $days = $state['days'];
    if ($days < 0) {
        $late = -$days;
        $text = $late === 1 ? t('{n} day overdue', ['{n}' => $late]) : t('{n} days overdue', ['{n}' => $late]);
        return '<span class="badge rounded-pill text-bg-danger fw-normal" title="' . t('Past its due date') . '"><i class="fas fa-exclamation-circle me-1"></i>' . $text . '</span>';
    }
    if ($days === 0) {
        return '<span class="badge rounded-pill text-bg-warning fw-normal"><i class="fas fa-clock me-1"></i>' . t('Due today') . '</span>';
    }
    $class = $days <= 7 ? 'bg-warning-subtle text-warning-emphasis' : 'bg-secondary-subtle text-secondary-emphasis';
    $text = $days === 1 ? t('Due in {n} day', ['{n}' => $days]) : t('Due in {n} days', ['{n}' => $days]);
    return '<span class="badge rounded-pill ' . $class . ' fw-normal"><i class="far fa-clock me-1"></i>' . $text . '</span>';
}
