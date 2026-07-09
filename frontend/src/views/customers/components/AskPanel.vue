<template>
  <AppModal
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    title="Ask about your customers"
    max-width="lg"
  >
    <div class="ask-body">
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

      <div v-if="error" class="ask-error">{{ error }}</div>

      <div v-if="answer" class="ask-answer">
        <p class="answer-text">{{ answer }}</p>

        <div v-if="sources.length" class="ask-sources">
          <p class="sources-label">Based on</p>
          <ul class="sources-list">
            <li v-for="s in sources" :key="`${s.customer_code}-${s.year}`" class="source-chip">
              {{ s.name }} ({{ s.customer_code }}, {{ s.year }})
            </li>
          </ul>
        </div>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import { ref, watch } from 'vue'
import api from '@/helpers/api'
import AppModal from '@/components/AppModal.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])

const question = ref('')
const asking = ref(false)
const answer = ref(null)
const sources = ref([])
const error = ref(null)

// reset each time the modal opens, so a previous answer doesn't linger
watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    question.value = ''
    answer.value = null
    sources.value = []
    error.value = null
  }
})

async function ask() {
  if (!question.value.trim() || asking.value) return

  asking.value = true
  answer.value = null
  sources.value = []
  error.value = null

  try {
    const { data } = await api.post('/api/customers/ask', { question: question.value })
    if (data.error) {
      error.value = data.error
    } else {
      answer.value = data.answer
      sources.value = data.sources ?? []
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Something went wrong'
  } finally {
    asking.value = false
  }
}
</script>

<style scoped>
.ask-body { padding: 20px 24px 28px; display: flex; flex-direction: column; gap: 16px; }

.ask-form { display: flex; gap: 10px; }
.ask-input {
  flex: 1; padding: 9px 14px; border-radius: 8px; border: 1px solid var(--color-border);
  background: var(--color-surface); font-size: 13px; font-family: inherit; color: var(--color-text);
}
.ask-input:focus { outline: none; border-color: var(--color-accent); }

.btn-primary {
  padding: 9px 20px; border-radius: 8px; border: none; background: var(--color-accent);
  cursor: pointer; font-size: 13px; font-weight: 600; color: #fff;
  font-family: inherit; display: flex; align-items: center; justify-content: center; gap: 8px;
  min-width: 64px;
}
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

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

.ask-answer {
  padding: 14px 16px; border-radius: 10px; background: var(--color-surface-hover);
  border: 1px solid var(--color-border);
}
.answer-text { font-size: 13.5px; color: var(--color-text); line-height: 1.6; white-space: pre-wrap; }

.ask-sources { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--color-border-subtle); }
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
