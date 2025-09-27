class ShtabRuleBuilder {
    constructor() {
        this.engine = null;
        this.editor = null;
        this.components = [];
        
        this.init();
    }

    init() {
        // Инициализация Rete.js редактора
        this.engine = new Rete.Engine('shtab-rule-builder');
        this.editor = new Rete.NodeEditor('shtab-rule-builder', this.engine);
        
        this.registerComponents();
        this.loadExampleRule();
    }

    registerComponents() {
        // Компонент для условий
        class ConditionComponent extends Rete.Component {
            constructor() {
                super("Условие");
            }

            builder(node) {
                // Логика построения узла условия
            }

            worker(node, inputs, outputs) {
                // Логика выполнения условия
            }
        }

        // Компонент для действий
        class ActionComponent extends Rete.Component {
            constructor() {
                super("Действие");
            }

            builder(node) {
                // Логика построения узла действия
            }

            worker(node, inputs, outputs) {
                // Логика выполнения действия
            }
        }

        this.editor.register(new ConditionComponent());
        this.editor.register(new ActionComponent());
    }

    loadExampleRule() {
        // Загрузка примера правила
    }

    saveRule() {
        const data = this.editor.toJSON();
        
        fetch('index.php?route=extension/module/shtab/repricing/saveRule&user_token=' + user_token, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showMessage('Правило сохранено!', 'success');
            } else {
                this.showMessage('Ошибка: ' + data.error, 'error');
            }
        });
    }

    showMessage(message, type) {
        // Показать уведомление
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible`;
        alert.innerHTML = `
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fa fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${message}
        `;
        
        document.querySelector('#content .container-fluid').prepend(alert);
        
        setTimeout(() => alert.remove(), 5000);
    }
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    window.ruleBuilder = new ShtabRuleBuilder();
});