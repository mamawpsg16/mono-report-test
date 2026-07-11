<template>
  <AppModal
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    title="Ask about your customers"
    max-width="lg"
  >
    <div class="ask-body">
      <div v-if="messages.length" ref="thread" class="ask-thread">
        <div v-for="(m, i) in messages" :key="i" class="ask-turn" :class="`ask-turn-${m.role}`">
          <p class="turn-text">{{ m.content }}</p>
          <div v-if="m.sources?.length" class="ask-sources">
            <p class="sources-label">Based on</p>
            <ul class="sources-list">
              <li v-for="s in m.sources" :key="`${s.customer_code}-${s.year}`" class="source-chip">
                {{ s.name }} ({{ s.customer_code }}, {{ s.year }})
              </li>
            </ul>
          </div>
        </div>

        <div v-if="error" class="ask-error">{{ error }}</div>
      </div>
      <p v-else class="ask-empty">Ask a question about your customers -- follow-ups remember what you asked before.</p>

      <form class="ask-form" @submit.prevent="ask">
        <input
          v-model="question"
          type="text"
          class="ask-input"
          placeholder="e.g. which customers are in Accra?"
          :disabled="asking"
        />
        <button class="btn-primary" type="submit" :disabled="asking || !question.trim()">
          <span v-if="asking" class="spinner"></span>
          <span v-else>Ask</span>
        </button>
      </form>
    </div>
  </AppModal>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import api from '@/helpers/api'
import AppModal from '@/components/AppModal.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])

const question = ref('')
const asking = ref(false)
const error = ref(null)
const messages = ref([])   // [{ role: 'user'|'assistant', content, sources? }]
const thread = ref(null)

// reset each time the modal opens, so a previous conversation doesn't linger
watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    question.value = ''
    error.value = null
    messages.value = []
  }
})

async function scrollToBottom() {
  await nextTick()
  if (thread.value) thread.value.scrollTop = thread.value.scrollHeight
}

async function ask() {
  const text = question.value.trim()
  if (!text || asking.value) return

  // history sent to the API is everything BEFORE this new question -- the
  // API expects role/content only, so strip the UI-only `sources` field
  const history = messages.value.map(({ role, content }) => ({ role, content }))

  messages.value.push({ role: 'user', content: text })
  question.value = ''
  asking.value = true
  error.value = null
  scrollToBottom()

  try {
    const { data } = await api.post('/api/customers/ask', { question: text, history })
    if (data.error) {
      error.value = data.error
    } else {
      messages.value.push({ role: 'assistant', content: data.answer, sources: data.sources ?? [] })
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Something went wrong'
  } finally {
    asking.value = false
    scrollToBottom()
  }
}
</script>

<style scoped>
.ask-body { padding: 20px 24px 24px; display: flex; flex-direction: column; gap: 14px; }

.ask-empty { font-size: 13px; color: var(--color-text-muted); padding: 8px 2px 4px; }

.ask-thread {
  display: flex; flex-direction: column; gap: 10px;
  max-height: 360px; overflow-y: auto; padding-right: 4px;
}

.ask-turn {
  padding: 12px 14px; border-radius: 10px; font-size: 13.5px; line-height: 1.6;
  max-width: 88%;
}
.ask-turn-user {
  align-self: flex-end; background: var(--color-accent-soft); color: var(--color-ink);
}
.ask-turn-assistant {
  align-self: flex-start; background: var(--color-surface-hover);
  border: 1px solid var(--color-border); color: var(--color-text);
}
.turn-text { white-space: pre-wrap; }

.ask-form { display: flex; gap: 10px; }
.ask-input {
  flex: 1; padding: 9px 14px; border-radius: 8px; border: 1px solid var(--color-border);
  background: var(--color-surface); font-size: 13px; font-family: inherit; color: var(--color-text);
}
.ask-input:focus { outline: none; border-color: var(--color-accent); }

/* base styles come from the global system in App.vue; keep only the min-width
   so the button doesn't shrink when its label swaps to the spinner */
.btn-primary { min-width: 64px; }

.spinner {
  width: 14px; height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.4); border-top-color: #fff;
  border-radius: 50%; animation: spin 0.6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.ask-error {
  padding: 10px 14px; border-radius: 8px; background: var(--color-danger-soft);
  border: 1px solid var(--color-danger-border); color: var(--color-danger); font-size: 12.5px;
}

.ask-sources { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--color-border-subtle); }
.sources-label {
  font-size: 10.5px; font-weight: 700; color: var(--color-text-muted);
  text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 8px;
}
.sources-list { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 6px; }
.source-chip {
  padding: 4px 10px; border-radius: 999px; background: var(--color-surface);
  border: 1px solid var(--color-border); font-size: 11.5px; font-weight: 500; color: var(--color-text-muted);
}
</style>
