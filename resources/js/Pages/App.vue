<template>
  <!-- Inertia handles routing. This is just a pass-through. -->
  <component :is="$page.component" v-bind="$page.props" />
</template>
